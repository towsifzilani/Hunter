<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;
    protected $fillable = ['conversation_id'];

    public function emails()
    {
        return $this->hasMany(Email::class);
    }
}
