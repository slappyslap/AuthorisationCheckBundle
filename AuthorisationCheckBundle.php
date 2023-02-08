<?php

namespace AuthorisationCheckBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class AuthorisationCheckBundle extends Bundle
{
    public function getPath(): string
    {
        return dirname(__DIR__);
    }
}