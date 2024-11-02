<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Webklex\IMAP\Facades\Client;
use DOMDocument;
use Carbon\Carbon;

class EmailControllerOld extends Controller
{
    public function fetchAndProcessEmails()
    {
        $client = Client::account('default');
        $client->connect();

        $folder = $client->getFolder('INBOX');
        $subjectToSearch = "Regarding Style BR006587 Interlining Query";
        $messages = $folder->messages()->subject($subjectToSearch)->get();
        dd($messages);
        $allParsedEmails = [];

        foreach ($messages as $message) {
            $htmlBody = $message->getHTMLBody(true);
            $subject = $message->get('subject');
            $date = $message->get('date');
            $from = $message->getFrom()[0]->mail;
            $to = $message->getTo()[0]->mail;
            $cc = $message->getCc()[0]->mail;

            $messageId = $message->get('message-id');
            $inReplyTo = $message->get('in-reply-to');
            $references = $message->get('references');
            $messageId = $messageId->get();
            $inReplyTo = $inReplyTo->get();
            // dd($messageId->get(), $inReplyTo->get(), $references);
            // Split the email thread
            $emailSegments = $this->splitMessages($htmlBody);
            
            foreach ($emailSegments as $segment) {
                $body = $this->extractBodyFromSegment($segment);
                $attachments = $this->extractAttachments($segment);

                $parsedEmailObject = [
                    'subject' => $subject,
                    'from' => $from,
                    'to' => $to,
                    'cc' => $cc,
                    'received_at' => $date,
                    'message_id' => $messageId,
                    'in_reply_to' => $inReplyTo,
                    'references' => $references,
                    'body' => $body,
                    'attachments' => $attachments,
                ];

                $allParsedEmails[] = $parsedEmailObject;
            }
        }

        $client->disconnect();

        return $allParsedEmails;
    }

    // Updated splitMessages function
    private function splitMessages($htmlBody)
    {
        // Pattern for detecting common reply markers and separators in the HTML
        $splitPattern = '/(?:<hr>|border-top:solid|From:\s.*?<br>|Sent:\s.*?<br>|<div class="gmail_quote">|<blockquote>)/i';
    
        // Split based on detected patterns
        $messages = preg_split($splitPattern, $htmlBody);
    
        // Clean up the array to remove any empty entries
        return array_filter(array_map('trim', $messages));
    }

    private function extractBodyFromSegment($htmlSegment)
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($htmlSegment);

        $body = '';
        foreach ($dom->getElementsByTagName('p') as $p) {
            $body .= $p->textContent . "\n";
        }

        return $body;
    }

    private function extractAttachments($htmlBody)
    {
        $attachments = [];
        preg_match_all('/src="data:(image\/[a-z]+);base64,([^"]+)/i', $htmlBody, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $mimeType = $match[1];
            $base64Data = $match[2];
            $fileContent = base64_decode($base64Data);

            $filename = 'attachment_' . md5($base64Data) . '.' . explode('/', $mimeType)[1];
            Storage::put("public/attachments/$filename", $fileContent);

            $attachments[] = [
                'filename' => $filename,
                'mime_type' => $mimeType,
                'path' => "public/attachments/$filename",
            ];
        }

        return $attachments;
    }
}
