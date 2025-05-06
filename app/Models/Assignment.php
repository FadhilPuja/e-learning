<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'assignments';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'assignment_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'description',
        'due_date',
        'file_url',
        'class_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'due_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the classroom that owns this assignment.
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id', 'class_id');
    }

    /**
     * Get the submissions for this assignment.
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'assignment_id', 'assignment_id');
    }
}