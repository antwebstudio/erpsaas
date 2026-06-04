<?php

namespace App\Models\Common;

class ClientAndLead extends Client
{
    public function getMorphClass(): string
    {
        return Client::class;
    }
}
