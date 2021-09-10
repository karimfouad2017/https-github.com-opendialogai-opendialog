/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

import 'core-js/features/promise'
import 'core-js/features/string'
import 'core-js/features/array'

import router from '@/router/index'

import OpenDialogAdmin from '@opendialogai/opendialog-design-system-pkg/src'
import PerfectScrollbar from 'vue2-perfect-scrollbar'
import VueCytoscape from 'vue-cytoscape'

import { BootstrapVue, BootstrapVueIcons } from 'bootstrap-vue'
import VueCookies from 'vue-cookies'

require('@/bootstrap');

window.Vue = require('vue');
window.Vue.use(OpenDialogAdmin);
window.Vue.use(BootstrapVue);
window.Vue.use(BootstrapVueIcons);
window.Vue.use(VueCookies);
window.Vue.use(PerfectScrollbar)
window.Vue.use(VueCytoscape)

/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

const { app } = new window.Vue({
  el: '#app',
  components: { OpenDialogAdmin },
  router,
});
