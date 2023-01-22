<?php


Route::group(['namespace' => 'Tan\ERP\Controllers', 'prefix' => 'erp'], function () {
    Route::post('webhook', 'IndexController@webhook');

    if (app()->environment('local')) {
        Route::get('api/{entityName}', 'IndexController@api');
    }
});
