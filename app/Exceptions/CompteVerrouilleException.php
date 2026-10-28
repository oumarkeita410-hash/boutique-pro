<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class CompteVerrouilleException extends Exception
{
    public function __construct()
    {
        parent::__construct("Votre compte est verrouillé. Contactez l'administrateur.");
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 403);
    }
}
