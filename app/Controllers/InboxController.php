<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

class InboxController
{
    public function index(Request $request, Response $response): void
    {
        $response->view('inbox.index', [
            'title' => 'Inbox',
            'pageTitle' => 'Inbox WhatsApp',
        ]);
    }
}
