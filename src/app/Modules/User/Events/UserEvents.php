<?php

namespace App\Modules\User\Events;

// Domain events dispatched by UserDocumentController and UserAuthController.
// Any listener in any module can subscribe — no direct coupling.

class UserCreated
{
    public function __construct(public readonly array $user) {}
}

class UserStatusChanged
{
    public function __construct(
        public readonly array  $user,
        public readonly string $oldStatus,
        public readonly string $newStatus,
    ) {}
}

class UserBanned
{
    public function __construct(public readonly array $user) {}
}

class UserLoggedIn
{
    public function __construct(
        public readonly array  $user,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {}
}

class UserLoggedOut
{
    public function __construct(public readonly array $user) {}
}
