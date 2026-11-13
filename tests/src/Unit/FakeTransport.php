<?php

declare(strict_types=1);

namespace Drupal\Tests\photos_sh\Unit;

use PhotosSh\Transport\Request;
use PhotosSh\Transport\Response;
use PhotosSh\Transport\Transport;

/**
 * A transport that records requests and answers with a fixed response.
 */
class FakeTransport implements Transport {

  /**
   * The requests seen.
   *
   * @var \PhotosSh\Transport\Request[]
   */
  public array $requests = [];

  public function __construct(protected Response $response = new Response(200, [], '{}')) {
  }

  /**
   * {@inheritdoc}
   */
  public function send(Request $request): Response {
    $this->requests[] = $request;
    return $this->response;
  }

}
