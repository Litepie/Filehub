export { default as FileHubUploader } from './components/FileHubUploader.vue';

// For use in Vue projects
export const install = (app) => {
  app.component('FileHubUploader', FileHubUploader);
};

export default {
  install
};
