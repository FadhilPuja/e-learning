<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassRoom extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'classes';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'class_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'unique_code',
        'created_by'
    ];

    /**
     * Get the teacher that owns the class.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    /**
     * Get the students for the class.
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'class_enrollments', 'class_id', 'user_id')
            ->withPivot('enrolled_at');
    }

    /**
     * Get the rooms for the class.
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class, 'class_id', 'class_id');
    }

    /**
     * Get the materials for the class.
     */
    public function materials(): HasMany
    {
        return $this->hasMany(Material::class, 'class_id', 'class_id');
    }
}