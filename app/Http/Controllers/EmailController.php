<?php

namespace App\Http\Controllers;

use Webklex\IMAP\Facades\Client;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Conversation;
use App\Models\Email;
use App\Models\Attachment;
use DB;

class EmailController extends Controller
{
    public function fetchEmails()
    {
        DB::beginTransaction();
        try {
            $client = Client::account('default');
            $client->connect();
    
            $folder = $client->getFolder('INBOX');
            $subjectToSearch = "Sample requisition & planning";
            $subjectToSearch = "Regarding Style BR006587 Interlining Query";
            $messages = $folder->messages()->subject($subjectToSearch)->get();
            dd($messages);
            foreach ($messages as $message) {
                $messageId = $message->getMessageId();
    
                if (Email::where('message_id', $messageId)->exists()) {
                    continue;
                }
    
                $inReplyTo = $message->getInReplyTo();
                $references = $message->getReferences();
                $subject = $message->getSubject();
                $from = $message->getFrom()[0]->mail;
    
                // Convert 'to' and 'cc' collections to comma-separated strings
                $to = $this->getEmailAddresses($message->getTo());
                $cc = $this->getEmailAddresses($message->getCc());
                $body = $message->getHTMLBody() ?: $message->getTextBody();
                $conversationId = $this->getOrCreateConversation($messageId, $inReplyTo, $references);
                $parentEmailId = $this->getParentEmailId($inReplyTo, $references);
    
                $references = $message->getReferences();
                $email = Email::create([
                    'conversation_id' => $conversationId,
                    'message_id' => $messageId,
                    'in_reply_to' => $inReplyTo,
                    'references' => $references ? json_encode($references) : null,
                    'from' => $from,
                    'to' => $to,
                    'cc' => $cc,
                    'subject' => $subject,
                    'body' => $body,
                    'parent_id' => $parentEmailId,
                ]);

                foreach ($message->getAttachments() as $attachment) {
                    $fileName = $attachment->name;
                    $filePath = "attachments/{$fileName}";
                    $content = $attachment->getContent();
    
                    Storage::put($filePath, $content);
    
                    Attachment::create([
                        'email_id' => $email->id,
                        'file_name' => $fileName,
                        'file_path' => $filePath,
                    ]);
                }
            }
    
            $client->disconnect();
            
            DB::commit();
            return $this->generateConversationResponse();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error($e->getMessage());
            throw new \Exception($e->getMessage());
        }
    }

    private function getEmailAddresses($collection)
    {
        $emails = [];
        foreach ($collection->toArray() as $item) {
            $emails[] = $item->mail;
        }
        return implode(',', $emails);
    }

    private function getOrCreateConversation($messageId, $inReplyTo, $references)
    {
        if(empty($inReplyTo->get()) && empty($references->get())) {
            $conversation = Conversation::create([
                'conversation_id' => (string) Str::uuid(),
            ]);
        
            return $conversation->id;
        }
        if (!empty($inReplyTo->get())) {
            $parentEmail = Email::where('message_id', $inReplyTo->get())->first();
            if ($parentEmail) {
                return $parentEmail->conversation_id;
            }
        }

        if (empty($inReplyTo->get()) && !empty($references->get())) {
            // Convert references to an array
            $referencesArray = $references && !empty($references->get())  ? $references->toArray() : []; // Convert to array or empty array if null

            // Filter out any empty values from references and get the last non-empty reference
            $filteredReferences = array_filter($referencesArray);
            $lastReference = end($filteredReferences);
            // dd($messageId);
    
            $parentEmail = Email::where('message_id', $lastReference)->first();
            if ($parentEmail) {
                return $parentEmail->conversation_id;
            }
    
            $referencedEmail = Email::where('references', 'LIKE', '%' . $lastReference . '%')->first();
            if ($referencedEmail) {
                return $referencedEmail->conversation_id;
            }
        }
    
        $conversation = Conversation::create([
            'conversation_id' => (string) Str::uuid(),
        ]);
    
        return $conversation->id;
    }
    

    private function getParentEmailId($inReplyTo, $references)
    {
        // Use In-Reply-To to find the direct parent email
        if (!empty($inReplyTo->get())) {
            $parentEmail = Email::where('message_id', $inReplyTo->get())->first();
            if ($parentEmail) {
                return $parentEmail->id;
            }
        }

        // If no In-Reply-To match, try using References chain
        if (!empty($references->get())) {
            foreach ($references as $ref) {
                $referencedEmail = Email::where('message_id', $ref)->first();
                if ($referencedEmail) {
                    return $referencedEmail->id;
                }
            }
        }

        return null;
    }

    private function generateConversationResponse()
    {
        $conversations = Conversation::with(['emails' => function ($query) {
            $query->orderBy('created_at');
        }])->get();

        $response = [];

        foreach ($conversations as $conversation) {
            $emails = $this->buildThread($conversation->emails);

            $response[] = [
                'conversation_id' => $conversation->id,
                'emails' => $emails,
            ];
        }

        return response()->json($response);
    }

    private function buildThread($emails, $parentId = null)
    {
        $thread = [];
        foreach ($emails->where('parent_id', $parentId) as $email) {
            $emailData = [
                'id' => $email->id,
                'message_id' => $email->message_id,
                'from' => $email->from,
                'to' => $email->to,
                'cc' => $email->cc,
                'subject' => $email->subject,
                'body' => $email->body,
                'attachments' => $email->attachments->map(function ($attachment) {
                    return [
                        'file_name' => $attachment->file_name,
                        'file_path' => Storage::url($attachment->file_path),
                    ];
                }),
            ];

            // Recursive call to get replies
            $emailData['replies'] = $this->buildThread($emails, $email->id);
            $thread[] = $emailData;
        }

        return $thread;
    }
}
