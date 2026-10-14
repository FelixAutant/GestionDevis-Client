<?php

namespace App\Entity;

/**
 * Resource that belongs to a single user (an artisan). Used by the voter to
 * make sure a user can only touch their own data.
 */
interface OwnedInterface
{
    public function getOwner(): ?User;
}
