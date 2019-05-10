<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    //return view('home');
    return redirect()->route('home');
});

// EVENTS: routing for the Event handling subsystem
Route::get('/events/poll','EventController@poll')->middleware('throttle:10,1')->name('events.poll');
Route::resource('events','EventController')->middleware('auth');
Route::get('/events','EventController@index')->middleware('auth')->name('events');
Route::get('/events/{event}', 'EventController@show')->name('events.show');

// LOGS: routing for the db_action logging subsystem
//Route::resource('dbActions', 'dbActionController');
Route::get('/logs', 'dbActionController@index')->name('logs');

// ROLES: routing for the role resource
Route::resource('roles','RoleController');
Route::get('/roles', 'RoleController@index')->name('roles');

// USERS: routing for the user resource
Route::resource('users','UserController');
Route::get('/users','UserController@index')->name('users');
Route::post('/users','UserController@winchattyLogin')->name('users.winchatty');

// POSTS: routing for the Post Mass Sync subsystem
Route::resource('posts','PostController');
Route::get('/posts', 'PostController@index')->middleware('auth')->name('posts');

// SEARCH: simple routing for the Search system
Route::get('/search', 'SearchController@index')->name('search');
Route::post('/search', 'SearchController@store')->middleware('auth')->name('search.store');

// WORD CLOUD: resource routing for the word_cloud resource
Route::resource('wordclouds','WordCloudController')->except(['show'])->middleware('auth');
Route::get('/wordclouds','WordCloudController@index')->name('wordclouds.index')->middleware('auth');
Route::get('/wordclouds','WordCloudController@index')->name('wordclouds')->middleware('auth');
Route::get('/wordclouds/{wordcloud}','WordCloudController@show')->name('wordclouds.show');
Route::get('/wordclouds/{wordcloud}/login', 'WordCloudController@show')->name('wordclouds.login')->middleware('auth');
Route::get('/wordclouds/{wordcloud}/download','WordCloudController@downloadPNG')->name('wordclouds.downloadPNG');
Route::get('/wordclouds/{wordcloud}/table','WordCloudController@table')->name('wordclouds.table');
Route::get('/wordclouds/{wordcloud}/table/login', 'WordCloudController@table')->name('wordclouds.table.login')->middleware('auth');
Route::get('/wordclouds/{wordcloud}/table/download','WordCloudController@downloadCSV')->name('wordclouds.downloadCSV');

// WORD CLOUD COLORSET: resource routing for the word_cloud_colorset resource
Route::delete('/colorsets/{color}/destroy','WordCloudColorsetController@destroyColor')->name('colorsets.destroycolor');
Route::resource('/colorsets','WordCloudColorsetController');
Route::get('/colorsets', 'WordCloudColorsetController@index')->name('colorsets');
Route::post('/colorsets/{colorset}','WordCloudColorsetController@storeColor')->name('colorsets.createcolor');

// THREADS: Add a set of resource routes for threads controller
Route::resource('threads', 'ThreadController');
Route::get('/threads', 'ThreadController@index')->name('threads');
Route::get('threads/{thread}/{post?}', 'ThreadController@show')->name('threads.show');

// APPSETTINGS: Add a set of resource routes for AppSetting controller
Route::resource('appsettings','AppSettingController');

// MONITORS: Add a set of resource routes for monitors controller
Route::resource('monitors', 'MonitorController');
Route::get('/monitors', 'MonitorController@index')->name('monitors');

/* The AUTH framework created this collection of routes automatically */
Route::get('users/{user}/profile','UserController@profile')->name('users.profile');
Route::put('users/{user}/profile','UserController@updateProfile')->name('users.updateprofile');
Route::get('users/{user}', function($user) {
    return redirect()->route('users.profile',$user);
})->name('users.show');
Auth::routes();

Route::get('/threads', 'ThreadController@index')->name('threads');
Route::get('/logs', 'dbActionController@index')->name('logs');
Route::get('/home', 'HomeController@index')->name('home');
Route::get('/appsettings','AppSettingController@index')->name('appsettings');

// TEST: for debugging Vue issues in IE11 with the wordclouds
//Route::view('/test','test');

//Route::get('/home', 'HomeController@index')->name('home');
