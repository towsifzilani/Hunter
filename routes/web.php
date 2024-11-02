<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Route;

Route::get('/', [EmailController::class, 'fetchEmails']);
