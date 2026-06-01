<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentDormitory extends Model
{
	protected $table = 'student_dormitories';

	protected $fillable = [
		'xh',
		'xm',
		'xy',
		'zy',
		'bj',
		'nj',
		'ssh',
		'ch',
		'xz',
		'qslx',
		'xb',
		'source_table',
		'synced_at',
	];

	protected $casts = [
		'synced_at' => 'datetime',
	];
}

