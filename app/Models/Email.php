<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Email extends Model
{
    use HasFactory;
    protected $fillable = [
        'conversation_id', 'message_id', 'in_reply_to', 'from', 'to', 'cc', 'subject', 'body','references','parent_id'
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }
}
