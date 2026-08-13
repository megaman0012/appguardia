<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class parametros extends Model {
    use HasFactory;
    protected $table = 'parametros';
    protected $primaryKey = 'pr_code';
    public $timestamps = false;
    protected $fillable = [
        'pr_descripcion',
        'pr_value',
    ];
}
