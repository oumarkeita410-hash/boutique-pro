<?php

use Illuminate\Support\Facades\Route;

// La page d'accueil du domaine ouvre directement l'application MOK-Business.
Route::redirect('/', '/app/');
