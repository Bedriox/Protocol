<?php

declare(strict_types=1);

namespace Bedriox\Protocol\GatewayControl;

enum ControlHandshakeStage: string
{
    case Hello = 'hello';
    case Challenge = 'challenge';
    case Authenticate = 'authenticate';
}
