<?php

declare(strict_types=1);

namespace Bedriox\Protocol\GatewayControl;

enum ControlMessageType: int
{
    case Hello = 1;
    case Challenge = 2;
    case Authenticate = 3;
    case Ready = 4;
    case HealthRequest = 10;
    case HealthResponse = 11;
    case ServerStatus = 12;
    case ServerStopping = 13;
    case DrainRequest = 14;
    case DrainResponse = 15;
    case PluginMessage = 20;
    case PluginMessageRejected = 21;
}
