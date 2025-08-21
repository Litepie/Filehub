import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';
import 'package:image_picker/image_picker.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:reorderable_list/reorderable_list.dart';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';
import 'dart:io';
import 'dart:convert';
import 'dart:async';

class FileHubFile {
  final String id;
  final String path;
  final String name;
  final String type;
  final int size;
  String title;
  String caption;
  bool uploading;
  double progress;
  String? error;
  Map<String, dynamic>? uploadedData;

  FileHubFile({
    required this.id,
    required this.path,
    required this.name,
    required this.type,
    required this.size,
    this.title = '',
    this.caption = '',
    this.uploading = false,
    this.progress = 0.0,
    this.error,
    this.uploadedData,
  });

  String get fileType {
    if (type.startsWith('image/')) return 'image';
    if (type.startsWith('video/')) return 'video';
    if (type.startsWith('audio/')) return 'audio';
    return 'document';
  }

  String get formattedSize {
    if (size == 0) return '0 Bytes';
    const int k = 1024;
    const List<String> sizes = ['Bytes', 'KB', 'MB', 'GB'];
    final int i = (size.bitLength / 10).floor();
    return '${(size / (1 << (i * 10))).toStringAsFixed(2)} ${sizes[i]}';
  }

  IconData get icon {
    switch (fileType) {
      case 'image':
        return Icons.image;
      case 'video':
        return Icons.videocam;
      case 'audio':
        return Icons.audiotrack;
      default:
        return Icons.description;
    }
  }
}

class FileHubUploader extends StatefulWidget {
  final String uploadUrl;
  final String uploadToken;
  final String collection;
  final bool multiple;
  final int maxSize; // KB
  final int maxFiles;
  final List<String> acceptedTypes;
  final bool showCaption;
  final Function(List<Map<String, dynamic>>)? onUploadSuccess;
  final Function(dynamic)? onUploadError;
  final Function(List<FileHubFile>)? onFilesChanged;
  final Function(FileHubFile, double)? onUploadProgress;
  final ThemeData? theme;

  const FileHubUploader({
    Key? key,
    this.uploadUrl = '/api/filehub/upload',
    required this.uploadToken,
    this.collection = 'default',
    this.multiple = true,
    this.maxSize = 10240,
    this.maxFiles = 10,
    this.acceptedTypes = const ['image', 'video', 'document'],
    this.showCaption = true,
    this.onUploadSuccess,
    this.onUploadError,
    this.onFilesChanged,
    this.onUploadProgress,
    this.theme,
  }) : super(key: key);

  @override
  _FileHubUploaderState createState() => _FileHubUploaderState();
}

class _FileHubUploaderState extends State<FileHubUploader>
    with TickerProviderStateMixin {
  List<FileHubFile> _files = [];
  bool _uploading = false;
  late AnimationController _animationController;
  late Animation<double> _animation;

  @override
  void initState() {
    super.initState();
    _animationController = AnimationController(
      duration: const Duration(milliseconds: 300),
      vsync: this,
    );
    _animation = CurvedAnimation(
      parent: _animationController,
      curve: Curves.easeInOut,
    );
  }

  @override
  void dispose() {
    _animationController.dispose();
    super.dispose();
  }

  ThemeData get _theme => widget.theme ?? Theme.of(context);

  Future<bool> _requestPermissions() async {
    if (Platform.isAndroid) {
      final Map<Permission, PermissionStatus> permissions = await [
        Permission.camera,
        Permission.storage,
        Permission.photos,
      ].request();

      return permissions.values.every(
        (status) => status == PermissionStatus.granted,
      );
    }
    return true;
  }

  String _generateId() {
    return DateTime.now().millisecondsSinceEpoch.toString() +
        (1000 + DateTime.now().microsecond % 1000).toString();
  }

  String? _validateFile(File file) {
    final int fileSizeKB = file.lengthSync() ~/ 1024;
    
    if (fileSizeKB > widget.maxSize) {
      return 'File size exceeds ${_formatFileSize(widget.maxSize * 1024)} limit';
    }

    // Basic file type validation based on extension
    final String extension = file.path.split('.').last.toLowerCase();
    const Map<String, List<String>> typeExtensions = {
      'image': ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'],
      'video': ['mp4', 'mov', 'avi', 'mkv', 'webm'],
      'audio': ['mp3', 'wav', 'ogg', 'aac', 'm4a'],
      'document': ['pdf', 'doc', 'docx', 'txt', 'rtf'],
    };

    bool isAccepted = false;
    for (String acceptedType in widget.acceptedTypes) {
      if (typeExtensions[acceptedType]?.contains(extension) == true) {
        isAccepted = true;
        break;
      }
    }

    if (!isAccepted) {
      return 'File type not allowed';
    }

    return null;
  }

  String _formatFileSize(int bytes) {
    if (bytes == 0) return '0 Bytes';
    const int k = 1024;
    const List<String> sizes = ['Bytes', 'KB', 'MB', 'GB'];
    final int i = (bytes.bitLength / 10).floor();
    return '${(bytes / (1 << (i * 10))).toStringAsFixed(2)} ${sizes[i]}';
  }

  String _getMimeType(String filePath) {
    final String extension = filePath.split('.').last.toLowerCase();
    const Map<String, String> mimeTypes = {
      'jpg': 'image/jpeg',
      'jpeg': 'image/jpeg',
      'png': 'image/png',
      'gif': 'image/gif',
      'webp': 'image/webp',
      'mp4': 'video/mp4',
      'mov': 'video/quicktime',
      'avi': 'video/x-msvideo',
      'pdf': 'application/pdf',
      'doc': 'application/msword',
      'docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'txt': 'text/plain',
      'mp3': 'audio/mpeg',
      'wav': 'audio/wav',
    };
    return mimeTypes[extension] ?? 'application/octet-stream';
  }

  void _addFiles(List<File> newFiles) {
    final List<FileHubFile> validFiles = [];
    final List<String> errors = [];

    for (File file in newFiles) {
      if (_files.length + validFiles.length >= widget.maxFiles) {
        errors.add('Maximum ${widget.maxFiles} files allowed');
        break;
      }

      final String? error = _validateFile(file);
      if (error != null) {
        errors.add('${file.path.split('/').last}: $error');
        continue;
      }

      // Check for duplicates
      final String fileName = file.path.split('/').last;
      final int fileSize = file.lengthSync();
      final bool isDuplicate = _files.any(
        (f) => f.name == fileName && f.size == fileSize,
      );
      if (isDuplicate) continue;

      final String fileId = _generateId();
      final String fileTitle = fileName.split('.').first;
      
      final FileHubFile fileData = FileHubFile(
        id: fileId,
        path: file.path,
        name: fileName,
        type: _getMimeType(file.path),
        size: fileSize,
        title: fileTitle,
      );

      validFiles.add(fileData);
    }

    if (errors.isNotEmpty) {
      widget.onUploadError?.call(errors.join('\n'));
    }

    if (validFiles.isNotEmpty) {
      setState(() {
        _files.addAll(validFiles);
      });
      _animationController.forward();
      widget.onFilesChanged?.call(_files);
    }
  }

  Future<void> _pickFromCamera() async {
    Navigator.of(context).pop();
    
    final bool hasPermission = await _requestPermissions();
    if (!hasPermission) {
      _showError('Camera permission is required');
      return;
    }

    try {
      final XFile? image = await ImagePicker().pickImage(
        source: ImageSource.camera,
        maxWidth: 1920,
        maxHeight: 1920,
        imageQuality: 85,
      );

      if (image != null) {
        _addFiles([File(image.path)]);
      }
    } catch (e) {
      _showError('Failed to capture from camera');
    }
  }

  Future<void> _pickFromGallery() async {
    Navigator.of(context).pop();
    
    try {
      if (widget.multiple) {
        final List<XFile> images = await ImagePicker().pickMultiImage(
          maxWidth: 1920,
          maxHeight: 1920,
          imageQuality: 85,
        );
        
        if (images.isNotEmpty) {
          _addFiles(images.map((image) => File(image.path)).toList());
        }
      } else {
        final XFile? image = await ImagePicker().pickImage(
          source: ImageSource.gallery,
          maxWidth: 1920,
          maxHeight: 1920,
          imageQuality: 85,
        );

        if (image != null) {
          _addFiles([File(image.path)]);
        }
      }
    } catch (e) {
      _showError('Failed to pick from gallery');
    }
  }

  Future<void> _pickFromFiles() async {
    Navigator.of(context).pop();
    
    try {
      FilePickerResult? result = await FilePicker.platform.pickFiles(
        allowMultiple: widget.multiple,
        type: FileType.any,
      );

      if (result != null) {
        final List<File> files = result.paths
            .where((path) => path != null)
            .map((path) => File(path!))
            .toList();
        
        if (files.isNotEmpty) {
          _addFiles(files);
        }
      }
    } catch (e) {
      _showError('Failed to pick files');
    }
  }

  void _showSourcePicker() {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (BuildContext context) {
        return Container(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text(
                'Select Source',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 20),
              ListTile(
                leading: const Icon(Icons.camera_alt),
                title: const Text('Camera'),
                onTap: _pickFromCamera,
              ),
              ListTile(
                leading: const Icon(Icons.photo_library),
                title: const Text('Photo Gallery'),
                onTap: _pickFromGallery,
              ),
              ListTile(
                leading: const Icon(Icons.folder),
                title: const Text('Files'),
                onTap: _pickFromFiles,
              ),
              const SizedBox(height: 10),
              TextButton(
                onPressed: () => Navigator.of(context).pop(),
                child: const Text('Cancel'),
              ),
            ],
          ),
        );
      },
    );
  }

  void _updateFile(String id, String field, String value) {
    setState(() {
      final int index = _files.indexWhere((f) => f.id == id);
      if (index != -1) {
        if (field == 'title') {
          _files[index].title = value;
        } else if (field == 'caption') {
          _files[index].caption = value;
        }
      }
    });
    widget.onFilesChanged?.call(_files);
  }

  void _removeFile(String id) {
    setState(() {
      _files.removeWhere((f) => f.id == id);
    });
    widget.onFilesChanged?.call(_files);
  }

  void _clearAll() {
    setState(() {
      _files.clear();
    });
    _animationController.reverse();
    widget.onFilesChanged?.call(_files);
  }

  Future<Map<String, dynamic>?> _uploadFile(FileHubFile file) async {
    try {
      final Uri uri = Uri.parse(widget.uploadUrl);
      final http.MultipartRequest request = http.MultipartRequest('POST', uri);
      
      request.headers['Accept'] = 'application/json';
      request.fields['collection'] = widget.collection;
      request.fields['title'] = file.title;
      request.fields['caption'] = file.caption;
      request.fields['upload_token'] = widget.uploadToken;

      final http.MultipartFile multipartFile = await http.MultipartFile.fromPath(
        'file',
        file.path,
        contentType: MediaType.parse(file.type),
      );
      request.files.add(multipartFile);

      final http.StreamedResponse response = await request.send();
      final String responseBody = await response.stream.bytesToString();

      if (response.statusCode == 200) {
        return json.decode(responseBody);
      } else {
        throw Exception('Upload failed with status ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('Upload failed: $e');
    }
  }

  Future<void> _startUpload() async {
    if (_uploading || _files.isEmpty) return;

    setState(() {
      _uploading = true;
    });

    final List<Map<String, dynamic>> uploadedFiles = [];
    final List<Map<String, dynamic>> errors = [];

    for (int i = 0; i < _files.length; i++) {
      final FileHubFile file = _files[i];
      
      try {
        setState(() {
          file.uploading = true;
          file.error = null;
        });

        // Simulate progress updates
        Timer.periodic(const Duration(milliseconds: 100), (timer) {
          if (file.progress >= 100) {
            timer.cancel();
          } else {
            setState(() {
              file.progress += 5;
            });
            widget.onUploadProgress?.call(file, file.progress);
          }
        });

        final Map<String, dynamic>? response = await _uploadFile(file);
        
        setState(() {
          file.uploading = false;
          file.progress = 100;
          file.uploadedData = response;
        });

        uploadedFiles.add({
          'file': file,
          'response': response,
        });
      } catch (e) {
        setState(() {
          file.uploading = false;
          file.error = e.toString();
        });

        errors.add({
          'file': file,
          'error': e,
        });
      }
    }

    setState(() {
      _uploading = false;
    });

    if (uploadedFiles.isNotEmpty) {
      widget.onUploadSuccess?.call(uploadedFiles);
    }

    if (errors.isNotEmpty) {
      widget.onUploadError?.call(errors);
    }

    // Remove successfully uploaded files
    setState(() {
      _files.removeWhere((f) => f.uploadedData != null);
    });
    widget.onFilesChanged?.call(_files);
  }

  void _showError(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: Colors.red,
      ),
    );
  }

  Widget _buildUploadZone() {
    return GestureDetector(
      onTap: _uploading ? null : _showSourcePicker,
      child: Container(
        width: double.infinity,
        height: 120,
        decoration: BoxDecoration(
          border: Border.all(
            color: _theme.primaryColor.withOpacity(0.3),
            width: 2,
            style: BorderStyle.solid,
          ),
          borderRadius: BorderRadius.circular(12),
          color: _theme.primaryColor.withOpacity(0.05),
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            if (_uploading)
              const CircularProgressIndicator()
            else ...[
              Icon(
                Icons.cloud_upload,
                size: 48,
                color: _theme.primaryColor,
              ),
              const SizedBox(height: 12),
              Text(
                'Tap to select files',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w600,
                  color: _theme.textTheme.bodyLarge?.color,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Support for multiple files, drag to reorder',
                style: TextStyle(
                  fontSize: 14,
                  color: _theme.textTheme.bodyMedium?.color?.withOpacity(0.7),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildFileItem(FileHubFile file) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      elevation: 2,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(8),
        side: BorderSide(
          color: file.uploading
              ? _theme.primaryColor
              : file.error != null
                  ? Colors.red
                  : Colors.transparent,
          width: file.uploading || file.error != null ? 2 : 0,
        ),
      ),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Drag handle
            if (!_uploading)
              Container(
                width: 24,
                height: 60,
                alignment: Alignment.center,
                child: Icon(
                  Icons.drag_handle,
                  color: _theme.textTheme.bodyMedium?.color?.withOpacity(0.5),
                ),
              ),
            
            // File preview
            Container(
              width: 60,
              height: 60,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(8),
                color: _theme.primaryColor.withOpacity(0.1),
              ),
              child: file.fileType == 'image'
                  ? ClipRRect(
                      borderRadius: BorderRadius.circular(8),
                      child: Image.file(
                        File(file.path),
                        width: 60,
                        height: 60,
                        fit: BoxFit.cover,
                        errorBuilder: (context, error, stackTrace) {
                          return Icon(
                            file.icon,
                            size: 30,
                            color: _theme.primaryColor,
                          );
                        },
                      ),
                    )
                  : Icon(
                      file.icon,
                      size: 30,
                      color: _theme.primaryColor,
                    ),
            ),
            
            const SizedBox(width: 12),
            
            // File info
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  TextField(
                    controller: TextEditingController(text: file.title),
                    decoration: const InputDecoration(
                      hintText: 'Enter file title...',
                      isDense: true,
                      border: OutlineInputBorder(),
                    ),
                    style: const TextStyle(fontWeight: FontWeight.w600),
                    enabled: !_uploading,
                    onChanged: (value) => _updateFile(file.id, 'title', value),
                  ),
                  
                  if (widget.showCaption) ...[
                    const SizedBox(height: 8),
                    TextField(
                      controller: TextEditingController(text: file.caption),
                      decoration: const InputDecoration(
                        hintText: 'Enter file description...',
                        isDense: true,
                        border: OutlineInputBorder(),
                      ),
                      maxLines: 2,
                      enabled: !_uploading,
                      onChanged: (value) => _updateFile(file.id, 'caption', value),
                    ),
                  ],
                  
                  const SizedBox(height: 8),
                  
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          file.name,
                          style: const TextStyle(fontSize: 12),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      Text(
                        file.formattedSize,
                        style: const TextStyle(fontSize: 12),
                      ),
                    ],
                  ),
                  
                  if (file.uploading) ...[
                    const SizedBox(height: 8),
                    LinearProgressIndicator(
                      value: file.progress / 100,
                      backgroundColor: Colors.grey[300],
                      valueColor: AlwaysStoppedAnimation<Color>(_theme.primaryColor),
                    ),
                  ],
                  
                  if (file.error != null) ...[
                    const SizedBox(height: 8),
                    Text(
                      file.error!,
                      style: const TextStyle(
                        color: Colors.red,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            
            // Remove button
            if (!_uploading || file.error != null)
              IconButton(
                icon: const Icon(Icons.close),
                color: Colors.red,
                onPressed: () => _removeFile(file.id),
                constraints: const BoxConstraints(
                  minWidth: 32,
                  minHeight: 32,
                ),
                padding: EdgeInsets.zero,
              ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _buildUploadZone(),
        
        if (_files.isNotEmpty) ...[
          const SizedBox(height: 16),
          Text(
            '${_files.length} file${_files.length != 1 ? 's' : ''} selected',
            style: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 16),
          
          AnimatedBuilder(
            animation: _animation,
            builder: (context, child) {
              return SizeTransition(
                sizeFactor: _animation,
                child: Container(
                  constraints: const BoxConstraints(maxHeight: 400),
                  child: ReorderableListView.builder(
                    shrinkWrap: true,
                    itemCount: _files.length,
                    itemBuilder: (context, index) {
                      final FileHubFile file = _files[index];
                      return ReorderableDelayedDragStartListener(
                        key: ValueKey(file.id),
                        index: index,
                        enabled: !_uploading,
                        child: _buildFileItem(file),
                      );
                    },
                    onReorder: (oldIndex, newIndex) {
                      setState(() {
                        if (newIndex > oldIndex) {
                          newIndex -= 1;
                        }
                        final FileHubFile item = _files.removeAt(oldIndex);
                        _files.insert(newIndex, item);
                      });
                      widget.onFilesChanged?.call(_files);
                    },
                  ),
                ),
              );
            },
          ),
          
          const SizedBox(height: 16),
          
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              OutlinedButton(
                onPressed: _uploading ? null : _clearAll,
                child: const Text('Clear All'),
              ),
              ElevatedButton(
                onPressed: _uploading || _files.isEmpty ? null : _startUpload,
                child: Text(
                  _uploading
                      ? 'Uploading...'
                      : 'Upload ${_files.length} file${_files.length != 1 ? 's' : ''}',
                ),
              ),
            ],
          ),
        ],
      ],
    );
  }
}
