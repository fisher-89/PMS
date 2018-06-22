<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateStaff extends Model
{

	/**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

   	protected $table = 'certificate_staff';

   	public function certificate()
   	{
   		return $this->belongsTo(Certificate::class, 'certificate_id', 'id');
   	}
}
