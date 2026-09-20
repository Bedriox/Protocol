<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum CommandOutputType: string
{
    case None = 'none';
    case LastOutput = 'lastoutput';
    case Silent = 'silent';
    case AllOutput = 'alloutput';
    case DataSet = 'dataset';
}
