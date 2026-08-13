<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMobileAppBusinessTables extends Migration{

    public function up(){

        Schema::create('organizacion_institucion', function (Blueprint $table) {
            $table->id('ins_code');
            $table->bigInteger('ins_so_code')->nullable();
            $table->string('ins_descripcion')->nullable();
            $table->string('ins_razon_social')->nullable();
            $table->string('ins_direccion')->nullable();
            $table->string('ins_ciudad')->nullable();
            $table->string('ins_telefono')->nullable();
            $table->string('ins_email')->nullable();
            $table->string('ins_tipo')->nullable();
            $table->boolean('ins_estado')->default(true);
            $table->bigInteger('ins_created_user')->nullable();
            $table->bigInteger('ins_updated_user')->nullable();
            $table->timestamps();
        });

        Schema::create('user_has_institucion', function (Blueprint $table) {
            $table->id('ui_code');
            $table->bigInteger('ui_usu_id');
            $table->bigInteger('ui_ins_code');
            $table->integer('ui_state')->default(1);
            $table->bigInteger('ui_created_user')->nullable();
            $table->bigInteger('ui_updated_user')->nullable();
            $table->timestamp('ui_created_at')->nullable();
            $table->timestamp('ui_updated_at')->nullable();
        });

        Schema::create('institucion_marcadores', function (Blueprint $table) {
            $table->id('im_code');
            $table->bigInteger('im_ins_code');
            $table->integer('im_numero')->nullable();
            $table->string('im_tipo')->nullable();
            $table->string('im_descripcion')->nullable();
            $table->string('im_lat')->nullable();
            $table->string('im_lng')->nullable();
            $table->boolean('im_estado')->default(true);
            $table->bigInteger('im_created_user')->nullable();
            $table->bigInteger('im_updated_user')->nullable();
            $table->timestamp('im_created_at')->nullable();
            $table->timestamp('im_updated_at')->nullable();
        });

        Schema::create('ronda_cabecera', function (Blueprint $table) {
            $table->id('rc_id');
            $table->bigInteger('rc_usu_code');
            $table->bigInteger('rc_ins_code');
            $table->string('rc_ug_code')->nullable();
            $table->timestamp('rc_fecha_inicio')->nullable();
            $table->timestamp('rc_fecha_fin')->nullable();
            $table->integer('rc_estado')->default(1);
            $table->string('rc_estado_ronda')->nullable();
            $table->text('rc_comentarios')->nullable();
            $table->string('rc_lat_inicio')->nullable();
            $table->string('rc_lng_inicio')->nullable();
            $table->string('rc_lat_fin')->nullable();
            $table->string('rc_lng_fin')->nullable();
            $table->bigInteger('rc_created_user')->nullable();
            $table->bigInteger('rc_updated_user')->nullable();
            $table->timestamp('rc_created_at')->nullable();
            $table->timestamp('rc_updated_at')->nullable();
        });

        Schema::create('ronda_detalle', function (Blueprint $table) {
            $table->id('rd_id');
            $table->bigInteger('rd_usu_id');
            $table->string('rd_ug_code')->nullable();
            $table->bigInteger('rd_ins_code');
            $table->bigInteger('rd_rc_id');
            $table->bigInteger('rd_im_code')->nullable();
            $table->text('rd_observacion')->nullable();
            $table->string('rd_foto')->nullable();
            $table->timestamp('rd_fecha_hora')->nullable();
            $table->integer('rd_estado')->default(1);
            $table->string('rd_lat')->nullable();
            $table->string('rd_lng')->nullable();
            $table->bigInteger('rd_created_user')->nullable();
            $table->bigInteger('rd_updated_user')->nullable();
            $table->timestamp('rd_created_at')->nullable();
            $table->timestamp('rd_updated_at')->nullable();
        });

        Schema::create('acceso_persona', function (Blueprint $table) {
            $table->id('ap_code');
            $table->string('ap_documento')->nullable();
            $table->string('ap_tip_doc')->nullable();
            $table->string('ap_nombres')->nullable();
            $table->string('ap_apellidos')->nullable();
            $table->boolean('ap_estado')->default(true);
            $table->bigInteger('ap_created_user')->nullable();
            $table->bigInteger('ap_updated_user')->nullable();
            $table->timestamp('ap_created_at')->nullable();
            $table->timestamp('ap_updated_at')->nullable();
        });

        Schema::create('acceso', function (Blueprint $table) {
            $table->id('ac_code');
            $table->bigInteger('ac_usu_id');
            $table->string('ac_ug_code')->nullable();
            $table->bigInteger('ac_ins_code');
            $table->integer('ac_tipo')->nullable();
            $table->integer('ac_is_entrada')->default(1);
            $table->timestamp('ac_is_salida_fecha')->nullable();
            $table->bigInteger('ac_ap_code')->nullable();
            $table->string('ac_lat')->nullable();
            $table->string('ac_lng')->nullable();
            $table->string('ac_lat_sal')->nullable();
            $table->string('ac_lng_sal')->nullable();
            $table->string('ac_empresa')->nullable();
            $table->string('ac_temperatura')->nullable();
            $table->string('ac_nombre_contrato')->nullable();
            $table->boolean('ac_bicicleta')->default(false);
            $table->boolean('ac_is_acomp')->default(false);
            $table->string('ac_nomb_acomp')->nullable();
            $table->string('ac_rut_acomp')->nullable();
            $table->string('ac_patente')->nullable();
            $table->boolean('ac_is_sello')->default(false);
            $table->boolean('ac_is_neumatico')->default(false);
            $table->boolean('ac_is_carro')->default(false);
            $table->boolean('ac_pta_llave')->default(false);
            $table->string('ac_kms')->nullable();
            $table->text('ac_observaciones')->nullable();
            $table->string('ac_foto')->nullable();
            $table->boolean('ac_estado')->default(true);
            $table->bigInteger('ac_created_user')->nullable();
            $table->bigInteger('ac_updated_user')->nullable();
            $table->timestamp('ac_created_at')->nullable();
            $table->timestamp('ac_updated_at')->nullable();
        });

        Schema::create('bitacora', function (Blueprint $table) {
            $table->id('bt_id');
            $table->bigInteger('bt_usu_id');
            $table->string('bt_ug_code')->nullable();
            $table->bigInteger('bt_ins_code');
            $table->text('bt_observacion')->nullable();
            $table->string('bt_foto')->nullable();
            $table->timestamp('bt_fecha_hora')->nullable();
            $table->integer('bt_estado')->default(1);
            $table->string('bt_lat')->nullable();
            $table->string('bt_lng')->nullable();
            $table->bigInteger('bt_created_user')->nullable();
            $table->bigInteger('bt_updated_user')->nullable();
            $table->timestamp('bt_created_at')->nullable();
            $table->timestamp('bt_updated_at')->nullable();
        });

        Schema::create('alertas', function (Blueprint $table) {
            $table->id('al_code');
            $table->bigInteger('al_ins_code');
            $table->bigInteger('al_usu_id');
            $table->string('al_lat')->nullable();
            $table->string('al_lng')->nullable();
            $table->string('al_anio')->nullable();
            $table->string('al_estado_alerta')->nullable();
            $table->integer('al_estado')->default(1);
            $table->text('al_observacion')->nullable();
            $table->timestamp('al_fecha')->nullable();
            $table->bigInteger('al_created_user')->nullable();
            $table->bigInteger('al_updated_user')->nullable();
            $table->timestamps();
        });

        Schema::create('novedad', function (Blueprint $table) {
            $table->id('nv_id');
            $table->bigInteger('nv_usu_id');
            $table->string('nv_ug_code')->nullable();
            $table->bigInteger('nv_ins_code');
            $table->text('nv_observacion')->nullable();
            $table->string('nv_foto')->nullable();
            $table->timestamp('nv_fecha_hora')->nullable();
            $table->integer('nv_estado')->default(1);
            $table->string('nv_lat')->nullable();
            $table->string('nv_lng')->nullable();
            $table->bigInteger('nv_created_user')->nullable();
            $table->bigInteger('nv_updated_user')->nullable();
            $table->timestamp('nv_created_at')->nullable();
            $table->timestamp('nv_updated_at')->nullable();
        });

        Schema::create('user_has_biometria', function (Blueprint $table) {
            $table->id('bio_code');
            $table->bigInteger('bio_user_id');
            $table->string('bio_ug_code')->nullable();
            $table->string('bio_image_name')->nullable();
            $table->string('bio_lat')->nullable();
            $table->string('bio_lng')->nullable();
            $table->integer('bio_is_entrada')->default(1);
            $table->bigInteger('bio_ins_code')->nullable();
            $table->boolean('bio_state')->default(true);
            $table->bigInteger('bio_created_user')->nullable();
            $table->bigInteger('bio_updated_user')->nullable();
            $table->timestamp('bio_created_at')->nullable();
            $table->timestamp('bio_updated_at')->nullable();
        });

        Schema::create('user_has_push_tkn', function (Blueprint $table) {
            $table->id('pt_code');
            $table->string('pt_token')->nullable();
            $table->bigInteger('pt_usu_id')->nullable();
            $table->bigInteger('pt_ins_id')->nullable();
            $table->string('pt_platform')->nullable();
            $table->string('pt_device_name')->nullable();
            $table->boolean('pt_active')->default(true);
            $table->string('pt_env')->nullable();
            $table->timestamp('pt_created_at')->nullable();
            $table->timestamp('pt_updated_at')->nullable();
        });

        Schema::create('inv_listas_productos', function (Blueprint $table) {
            $table->id('lp_id');
            $table->bigInteger('lp_ins_code');
            $table->string('lp_nombre')->nullable();
            $table->string('lp_descripcion')->nullable();
            $table->integer('lp_estado')->default(1);
            $table->bigInteger('lp_created_user')->nullable();
            $table->bigInteger('lp_updated_user')->nullable();
            $table->timestamp('lp_created_at')->nullable();
            $table->timestamp('lp_updated_at')->nullable();
        });

        Schema::create('inv_productos', function (Blueprint $table) {
            $table->id('pr_id');
            $table->string('pr_nombre')->nullable();
            $table->string('pr_descripcion')->nullable();
            $table->string('pr_especificacion')->nullable();
            $table->decimal('pr_stock_actual', 12, 2)->nullable();
            $table->integer('pr_estado')->default(1);
            $table->bigInteger('pr_created_user')->nullable();
            $table->bigInteger('pr_updated_user')->nullable();
            $table->timestamp('pr_created_at')->nullable();
            $table->timestamp('pr_updated_at')->nullable();
        });

        Schema::create('inv_lista_producto_items', function (Blueprint $table) {
            $table->id('lpi_id');
            $table->bigInteger('lpi_lp_id');
            $table->bigInteger('lpi_pr_id');
            $table->decimal('lpi_cantidad', 12, 2)->nullable();
            $table->integer('lpi_estado')->default(1);
            $table->bigInteger('lpi_created_user')->nullable();
            $table->bigInteger('lpi_updated_user')->nullable();
            $table->timestamp('lpi_created_at')->nullable();
            $table->timestamp('lpi_updated_at')->nullable();
        });

        Schema::create('inv_movimientos', function (Blueprint $table) {
            $table->id('mov_id');
            $table->bigInteger('mov_ins_code');
            $table->bigInteger('mov_lp_id')->nullable();
            $table->string('mov_tipo')->nullable();
            $table->bigInteger('mov_recep_asig_user')->nullable();
            $table->timestamp('mov_recep_asig_fecha')->nullable();
            $table->string('mov_recep_asig_obsv')->nullable();
            $table->bigInteger('mov_recep_user')->nullable();
            $table->timestamp('mov_recep_fecha')->nullable();
            $table->string('mov_recep_obsv')->nullable();
            $table->string('mov_recep_lat')->nullable();
            $table->string('mov_recep_lng')->nullable();
            $table->bigInteger('mov_devol_user')->nullable();
            $table->timestamp('mov_devol_fecha')->nullable();
            $table->string('mov_devol_obsv')->nullable();
            $table->string('mov_devol_lat')->nullable();
            $table->string('mov_devol_lng')->nullable();
            $table->bigInteger('mov_devol_entreg_user')->nullable();
            $table->timestamp('mov_devol_entreg_fecha')->nullable();
            $table->string('mov_devol_entreg_obsv')->nullable();
            $table->bigInteger('mov_created_user')->nullable();
            $table->bigInteger('mov_updated_user')->nullable();
            $table->integer('mov_estado')->default(1);
            $table->timestamp('mov_created_at')->nullable();
            $table->timestamp('mov_updated_at')->nullable();
        });

        Schema::create('inv_movimiento_detalles', function (Blueprint $table) {
            $table->id('md_id');
            $table->bigInteger('md_mov_id');
            $table->bigInteger('md_pr_id');
            $table->decimal('md_cant_asign', 12, 2)->nullable();
            $table->decimal('md_cant_recep', 12, 2)->nullable();
            $table->string('md_recep_obsv')->nullable();
            $table->decimal('md_cant_devol', 12, 2)->nullable();
            $table->decimal('md_cant_final', 12, 2)->nullable();
            $table->integer('md_exist')->nullable();
            $table->integer('md_estado')->default(1);
            $table->bigInteger('md_created_user')->nullable();
            $table->bigInteger('md_updated_user')->nullable();
            $table->timestamp('md_created_at')->nullable();
            $table->timestamp('md_updated_at')->nullable();
        });
    }

    public function down(){
        $tables = [
            'inv_movimiento_detalles', 'inv_movimientos', 'inv_lista_producto_items',
            'inv_productos', 'inv_listas_productos', 'user_has_push_tkn',
            'user_has_biometria', 'novedad', 'alertas', 'bitacora', 'acceso',
            'acceso_persona', 'ronda_detalle', 'ronda_cabecera',
            'institucion_marcadores', 'user_has_institucion', 'organizacion_institucion',
        ];
        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
}
