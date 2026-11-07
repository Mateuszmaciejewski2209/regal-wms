<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Skany z telefonów – widoczne tylko dla właściciela urządzenia.
Broadcast::channel('scanner.{userId}', fn (User $user, int $userId) => $user->is_active && $user->id === $userId);

// Zmiany dokumentów i stanów – dla wszystkich aktywnych użytkowników.
Broadcast::channel('warehouse', fn (User $user) => $user->is_active);

// Pozycje konkretnego dokumentu (edytor na żywo).
Broadcast::channel('documents.{documentId}', fn (User $user, int $documentId) => $user->is_active);
