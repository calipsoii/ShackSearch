
/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require('./bootstrap');

window.Vue = require('vue');

/* https://laracasts.com/discuss/channels/laravel/how-to-require-npm-packages-after-installing-it-in-laravel */
window.html2canvas = require('html2canvas');

/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

//import html2canvas from 'html2canvas';
import VueWordCloud from 'vuewordcloud';

Vue.component('example-component', require('./components/ExampleComponent.vue'));
Vue.component(
    VueWordCloud.name,
    VueWordCloud
);

const app = new Vue({
    el: '#app',
    methods: {
        onWordClick: function(word,from,to,author,dailyuser) {
            var searchURL = '';
            if(author === dailyuser) {
                searchURL = 'https://nullterminated.org/search?body=%22'+word+'%22&daterange=custom&from='+from+'&to='+to+'&sort=desc&engine=common&linktarget=local';
            } else {
                searchURL = 'https://nullterminated.org/search?body=%22'+word+'%22&author='+author+'&daterange=custom&from='+from+'&to='+to+'&sort=desc&engine=common&linktarget=local';
            }
            
            location.href=searchURL;
        },
        createCanvas: function() {
            return document.createElement('canvas');
        }
    },
});

/**
 * Trying to import the vuewordcloud npm package as per:
 * 
 * https://stackoverflow.com/questions/48925886/import-vue-package-in-laravel
 * https://medium.com/@damijolayemi/workflow-tip-how-to-use-vuejs-in-a-laravel-package-71fef6ea1d12
 * https://github.com/SeregPie/VueWordCloud
 * 
 * https://laravel.com/docs/5.6/frontend#writing-vue-components
 * 
 * Amazingly, when I run npm run dev after adding the below, it works without errors. Hope that's a good thing.
 */