<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Request;
use App\Services\FirebaseService;

class MessageController extends Controller
{
    protected $database;

    public function __construct(FirebaseService $firebase)
    {
        $this->database = $firebase->database;
    }
    public function getMessages($friendId)
    {
        $messages = Message::where(function($q) use ($friendId) {
                $q->where('sender_id', auth()->id())
                  ->where('receiver_id', $friendId);
            })
            ->orWhere(function($q) use ($friendId) {
                $q->where('sender_id', $friendId)
                  ->where('receiver_id', auth()->id());
            })
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($messages);
    }

    public function sendMessage(Request $request)
    {
        $data = $request->validate([
            'receiver_id' => 'required|integer',
            'message' => 'required|string'
        ]);

        $message = Message::create([
            'sender_id' => auth()->id(),
            'receiver_id' => $data['receiver_id'],
            'message' => $data['message']
        ]);

        // Firebase Push
        $chatId = $this->getChatId(auth()->id(), $data['receiver_id']);

        $this->database->getReference("chats/{$chatId}/messages")->push([
            'text' => $data['message'],
            'sender_id' => auth()->id(),
            'timestamp' => now()->timestamp
        ]);

        return response()->json($message);
    }

    private function getChatId($user1, $user2)
    {
        return $user1 < $user2 ? "chat_{$user1}_{$user2}" : "chat_{$user2}_{$user1}";
    }
}
