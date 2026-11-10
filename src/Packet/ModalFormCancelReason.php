<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum ModalFormCancelReason: int
{
    case UserClosed = 0;
    case UserBusy = 1;
}
