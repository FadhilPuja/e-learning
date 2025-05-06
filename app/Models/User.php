<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'image_url',
        'phone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Check if user is a teacher
     * 
     * @return bool
     */
    public function isTeacher(): bool
    {
        return $this->role === 'Teacher';
    }

    /**
     * Check if user is a student
     * 
     * @return bool
     */
    public function isStudent(): bool
    {
        return $this->role === 'Student';
    }

    /**
     * Get the classes created by this teacher
     * 
     * @return HasMany
     */
    public function createdClasses(): HasMany
    {
        return $this->hasMany(ClassRoom::class, 'created_by');
    }

    /**
     * Get the classes this student is enrolled in
     * 
     * @return BelongsToMany
     */
    public function enrolledClasses(): BelongsToMany
    {
        return $this->belongsToMany(ClassRoom::class, 'class_enrollments', 'user_id', 'class_id')
                    ->using(ClassEnrollment::class)
                    ->withPivot('enrolled_at');
    }

    /**
     * Get the class enrollments for this user
     * 
     * @return HasMany
     */
    public function classEnrollments(): HasMany
    {
        return $this->hasMany(ClassEnrollment::class, 'user_id');
    }
}