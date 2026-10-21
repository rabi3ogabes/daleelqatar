<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/** A business rule prevented the requested state change. Rendered as 422 (API) or a flash error (web). */
class WorkflowException extends DomainException {}
