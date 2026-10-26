<?php

namespace App\Models;

use Database\Factories\SuccessorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'structure_position_key',
    'name',
    'position',
    'talent_mapping',
    'succession_readiness',
    'idp',
    'education',
    'tenure',
    'directorate',
    'photo_path',
])]
class Successor extends Model
{
    /** @use HasFactory<SuccessorFactory> */
    use HasFactory;
}
