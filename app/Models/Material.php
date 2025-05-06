<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Material extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'materials';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'material_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'description',
        'content',
        'class_id'
    ];

    /**
     * Get the classroom that owns this material.
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id', 'class_id');
    }
}