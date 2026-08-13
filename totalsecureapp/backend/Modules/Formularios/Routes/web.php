<?php
Route::prefix('formularios')->middleware(['web', 'auth'])->group(function() {

    Route::middleware(['permission:formularios/epicrisis.index'])->group(function () {
        Route::get( 'epicrisis.index'        , 'Form006Controller@index'    );
        Route::post('epicrisis.getbydoc'     , 'Form006Controller@getbydoc' );
        Route::get( 'epicrisis.document/{id}', 'Form006Controller@document' );
    });

    Route::middleware(['permission:formularios/referencia.index'])->group(function () {
        Route::get( 'referencia.index'       , 'Form053Controller@index'    );
    });

});
