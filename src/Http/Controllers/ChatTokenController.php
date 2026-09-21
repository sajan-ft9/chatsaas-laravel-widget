<?php

namespace Chatsaas\LaravelWidget\Http\Controllers;

use Chatsaas\LaravelWidget\AssistantIdentity;
use Illuminate\Http\Request;

class ChatTokenController
{
    public function refresh(Request $request)
    {
        return response(AssistantIdentity::tokenFor($request->user()))
            ->header('Content-Type', 'text/plain');
    }
}
