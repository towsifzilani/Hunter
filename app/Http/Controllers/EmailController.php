<?php

namespace App\Http\Controllers;

use Webklex\IMAP\Facades\Client;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Conversation;
use App\Models\Email;
use App\Models\Attachment;
use DB;
use Carbon\Carbon;

class EmailController extends Controller
{
    public function fetchEmails()
    {
        DB::beginTransaction();
        try {
            $client = Client::account('default');
            $client->connect();
            // dd('test');
            $folder = $client->getFolder('INBOX');
            $messages = $folder->messages()
                        ->all()
                        ->get();
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
                $to = $this->getEmailAddresses($message->getTo());
                $cc = $this->getEmailAddresses($message->getCc());
                $htmlBody = $message->getHTMLBody() ?: $message->getTextBody();

                $conversationId = $this->getOrCreateConversation($messageId, $inReplyTo, $references);
                $date = Carbon::parse($message->getDate()[0]);

                $ref = $references && !empty($references->get())  ? $references->toArray() : [];
                
                $email = Email::create([
                    'conversation_id' => $conversationId,
                    'folder_name' => $message->getFolderPath(),
                    'message_id' => $messageId,
                    'in_reply_to' => $inReplyTo,
                    'references' => $ref ? json_encode($ref) : null,
                    'from' => $from,
                    'to' => $to,
                    'cc' => $cc,
                    'subject' => $subject,
                    'body' => $htmlBody,
                    'sentDateTime' => $date,
                    'receivedDateTime' => $date,
                ]);

                $cidToUrlMap = [];

                foreach ($message->getAttachments() as $attachment) {
                    $fileName = $attachment->name;
                    $filePath = "attachments/{$fileName}";
                    $content = $attachment->getContent();
                    
                    Storage::put('public/'.$filePath, $content);
            
                    $attachmentRecord = Attachment::create([
                        'email_id' => $email->id,
                        'content_id' => $attachment->getId(),
                        'file_name' => $fileName,
                        'file_path' => $filePath,
                    ]);
            
                    if ($attachment->getContentId()) {
                        $cidToUrlMap[$attachment->getId()] = url('storage/'.$filePath);
                    }
                }
            
                foreach ($cidToUrlMap as $cid => $url) {
                    $htmlBody = str_replace("src=\"cid:{$cid}\"", "src=\"{$url}\"", $htmlBody);
                }
            
                $email->update(['body' => $htmlBody]);
            }
    
            $client->disconnect();
            
            DB::commit();

            return view('email-automation', ['emails' => $this->generateConversationResponse()]);
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

    private function getOrCreateConversation($messageId, &$inReplyTo, $references)
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

            $referencedEmail = Email::whereJsonContains('references', $inReplyTo->get())->first();
            if ($referencedEmail) {
                return $referencedEmail->conversation_id;
            }
        }

        if (empty($inReplyTo->get()) && !empty($references->get())) {
            $inReplyTo = null;
            $referencesArray = $references && !empty($references->get())  ? $references->toArray() : [];
            $filteredReferences = array_filter($referencesArray);
            $lastReference = end($filteredReferences);
            $inReplyTo = $lastReference;
    
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

    private function generateConversationResponse()
    {
        $emails = Email::with('conversation','attachments')->get();

        $response = $emails->map(function ($email) {
            return [
                'id' => $email->id,
                'conversationId' => $email->conversation->conversation_id,
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
        });

        return $response;
    }
}
