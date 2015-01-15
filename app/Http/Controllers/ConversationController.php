<?php namespace Strimoid\Http\Controllers;

use Auth;
use Strimoid\Models\Conversation;

class ConversationController extends BaseController {

    public function showConversation($id = null)
    {
        $conversations = Conversation::with('lastMessage')->where('users', Auth::user()->getKey())->get()
            ->sortBy(function($conversation){
                return $conversation->lastMessage->created_at;
            })->reverse();

        $data['messages'] = array();

        if ($id)
            $conversation = Conversation::where('users', Auth::user()->getKey())->findOrFail($id);
        elseif ($conversations->first())
            $conversation = $conversations->first();

        $data['conversations'] = $conversations;

        if (isset($conversation))
        {
            $data['conversation'] = $conversation;
            $data['messages'] = ConversationMessage::where('conversation_id', $conversation->_id)
                ->orderBy('created_at', 'desc')->paginate(50);
        }

        return view('conversations.display', $data);
    }

    public function showCreateForm($username = null)
    {
        $conversations = Conversation::with('lastMessage')->where('users', Auth::user()->getKey())->get()
            ->sortBy(function($conversation){
                return $conversation->lastMessage->created_at;
            })->reverse();

        return view('conversations.create', compact('conversations', 'username'));
    }

    public function createConversation()
    {
        $target = User::where('shadow_name', Str::lower(Input::get('username')))->firstOrFail();

        if ($target->getKey() == Auth::user()->getKey())
        {
            return Redirect::action('ConversationController@showCreateForm')
                ->withInput()
                ->with('danger_msg', 'Ekhm... wysyłanie wiadomości do samego siebie chyba nie ma sensu ;)');
        }

        if ($target->isBlockingUser(Auth::user()))
        {
            return Redirect::action('ConversationController@showCreateForm')
                ->withInput()
                ->with('danger_msg', 'Zostałeś zablokowany przez wybranego użytkownika.');
        }

        $validator = Validator::make(Input::all(), ['text' => 'required|max:10000',]);

        if ($validator->fails())
        {
            return Redirect::action('ConversationController@showCreateForm')
                ->withInput()
                ->withErrors($validator);
        }

        $conversation = Conversation::where('users', Auth::user()->getKey())
            ->where('users', $target->getKey())
            ->first();

        if (!$conversation)
        {
            $conversation = new Conversation();
            $conversation->_id = Str::random(8);
            $conversation->users = array(Auth::user()->getKey(), $target->getKey());
            $conversation->save();
        } else {
            Notification::where('type', 'conversation')
                ->where('conversation_id', $conversation->_id)
                ->where('user_id', $target->_id)
                ->delete();
        }

        $message = new ConversationMessage();
        $message->conversation()->associate($conversation);
        $message->user()->associate(Auth::user());
        $message->text = Input::get('text');
        $message->save();

        $this->sendNotifications([$target->_id], function($notification) use ($message, $conversation)
        {
            $notification->type = 'conversation';
            $notification->setTitle($message->text);
            $notification->conversation()->associate($conversation);
            $notification->save(); // todo
        });

        return Redirect::to('/conversations');
    }

    public function sendMessage()
    {
        $conversation = Conversation::where('users', Auth::user()->getKey())
            ->where('_id', Input::get('id'))->firstOrFail();

        $target = $conversation->getUser();

        if ($target->isBlockingUser(Auth::user())) {
            return Redirect::route('conversation', $conversation->_id)
                ->withInput()
                ->with('danger_msg', 'Zostałeś zablokowany przez wybranego użytkownika.');
        }

        $rules = ['text' => 'required|max:10000'];

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails())
        {
            Input::flash();
            return Redirect::route('conversation', $conversation->_id)->withErrors($validator);
        }

        Notification::where('type', 'conversation')->where('conversation_id', $conversation->_id)
            ->where('user_id', $target->_id)->delete();

        $message = new ConversationMessage();
        $message->conversation()->associate($conversation);
        $message->user()->associate(Auth::user());
        $message->text = Input::get('text');
        $message->save();

        $this->sendNotifications([$target->_id], function($notification) use ($message, $conversation)
        {
            $notification->type = 'conversation';
            $notification->setTitle($message->text);
            $notification->conversation()->associate($conversation);
            $notification->save(); // todo
        });

        return Redirect::route('conversation', $conversation->_id);
    }

    public function getIndex()
    {
        $conversations = Conversation::with('lastMessage')->with('lastMessage.user')
            ->where('users', Auth::user()->getKey())
            ->get()
            ->sortBy(function($conversation){
                return $conversation->lastMessage->created_at;
            })->reverse();

        return $conversations;
    }

    public function getMessages()
    {
        $ids = Conversation::where('users', Auth::user()->getKey())
            ->lists('_id');

        $messages = ConversationMessage::with('conversation')->with('user')
            ->whereIn('conversation_id', $ids)
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return $messages;
    }

}