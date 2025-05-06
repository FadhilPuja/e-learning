<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ClassEnrollment extends Pivot
{
    protected $table = 'class_enrollments';
    
    protected $fillable = [
        'class_id',
        'user_id',
        'enrolled_at'
    ];

    /**
     * Get the class for this enrollment
     */
    public function classroom()
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    /**
     * Get the student for this enrollment
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}