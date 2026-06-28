<?php

namespace MacropaySolutions\CrufdWizard\Responses;

use MacropaySolutions\Kernel\Contracts\Support\Arrayable;
use MacropaySolutions\Kernel\Contracts\Support\Jsonable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * StreamedJsonResponse represents a streamed HTTP response for JSONs
 *
 * Example usage:
 *
 *     $response = new StreamedJsonResponse(Operation::query()->with('client')->lazyByIdDesc(1000, 'id'), 200, [
 *         StreamedJsonResponse::META_DATA => \json_encode(\array_merge([ // use this only if you want metaData info
 *             'has_more_pages' => false,
 *         ], $appends, $paginator instanceof Paginator ? [
 *             'current_page' => 1,
 *             'from' => 1,
 *             'per_page' => $paginator->perPage(),
 *             'to' => $paginator->perPage(),
 *             'data' => [],
 *         ] : [
 *             'current_page' => 1,
 *             'from' => 1,
 *             'last_page' => 1,
 *             'per_page' => $paginator->total(),
 *             'to' => $paginator->total(),
 *             'total' => $paginator->total(),
 *             'data' => [],
 *         ]))
 *     ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); // DO NOT use JSON_PRETTY_PRINT to avoid issues with \n
 *
 *  Example streamed response:
 *  Header:
 *
 *  metaData: {
 *      "has_more_pages": false,
 *      "sums": {
 *          "value": null
 *      },
 *      "avgs": {
 *          "value_avg": null
 *      },
 *      "mins": {
 *          "value_min": null,
 *          "created_at_min": null
 *      },
 *      "maxs": {
 *          "value_max": null,
 *          "created_at_max": null
 *      },
 *      "index_required_on_filtering": [
 *          "id",
 *          "created_at"
 *      ],
 *      "current_page": 1,
 *      "from": 1,
 *      "last_page": 1,
 *      "per_page": 17009,
 *      "to": 17009,
 *      "total": 17009,
 *      "data": []
 *  }
 *
 *  Body:
 *
 *    {"id":17009,"value":"92.00","created_at":"2024-01-17 09:17:11","updated_at":null,"primary_key_identifier":"17009"}
 *    {"id":17008,"value":"87.00","created_at":"2024-01-17 09:17:11","updated_at":null,"primary_key_identifier":"17008"}
 *    ...
 *
 * Example javascript code to handle this response:
 *
 * <script>
 *      function requestStream(e,form) {
 *          e.preventDefault();
 *          const outputElement = document.getElementById("streamed_operations_response");
 *
 *          outputElement.innerHTML = '';
 *          fetch(form.action, {method:'post', headers: {
 *                  'Accept': 'application/json'
 *              }, body: new URLSearchParams(new FormData(form))})
 *              .then(response => {
 *                  const reader = response.body.getReader();
 *                  const decoder = new TextDecoder();
 *                  let leftOver = '';
 *                  return new ReadableStream({
 *                      start(controller) {
 *                          function push() {
 *                              reader.read().then(({ done, value }) => {
 *                                  if (done) {
 *                                      controller.close();
 *                                      return;
 *                                  }
 *                                  const chunk = decoder.decode(value, { stream: true });
 *                                  chunk.split(/\n/).forEach(function (element) {
 *                                      let row;
 *                                      try {
 *                                          row = JSON.parse(element);
 *                                      } catch (e) {
 *                                          console.log('e');
 *                                          if (leftOver === '') {
 *                                              leftOver = element;
 *
 *                                              return;
 *                                          }
 *                                          try {
 *                                              row = JSON.parse(leftOver + element);
 *                                              leftOver = '';
 *                                          } catch (ex) {
 *                                              console.log('ex');
 *                                              leftOver += element;
 *                                              console.log('This leftOver should not happen: ' + leftOver);
 *
 *                                              return;
 *                                          }
 *                                      }
 *
 *                                      let child = document.createElement('p');
 *                                      child.innerHTML = JSON.stringify(row);
 *                                      outputElement.appendChild(child);
 *                                  });
 *                                  controller.enqueue(value);
 *                                  push();
 *                              });
 *                          }
 *                          push();
 *                      }
 *                  });
 *              })
 *              .then(stream => new Response(stream))
 *              .then(response => response.text())
 *              .then(data => {
 *                  console.log("Streaming complete");
 *              })
 *              .catch(error => {
 *                  console.error("Streaming error:", error);
 *              });
 *      }
 *  </script>
 */
class StreamedJsonResponse extends StreamedResponse
{
    public const META_DATA = 'metaData';

    public function __construct(
        private iterable $data,
        int $status = 200,
        array $headers = [],
        private int $encodingOptions = JsonResponse::DEFAULT_ENCODING_OPTIONS,
    ) {
        parent::__construct(function (): void {
            $prefix = null;

            foreach ($this->data as $item) {
                if ($item instanceof Jsonable) {
                    echo $prefix . $item->toJson(\JSON_THROW_ON_ERROR | $this->encodingOptions);
                    \ob_flush();
                    \flush();
                    $prefix ??= "\n";

                    continue;
                }

                if ($item instanceof \JsonSerializable) {
                    echo $prefix . \json_encode($item->jsonSerialize(), \JSON_THROW_ON_ERROR | $this->encodingOptions);
                    \ob_flush();
                    \flush();
                    $prefix ??= "\n";

                    continue;
                }

                if ($item instanceof Arrayable) {
                    echo $prefix . \json_encode($item->toArray(), \JSON_THROW_ON_ERROR | $this->encodingOptions);
                    \ob_flush();
                    \flush();
                    $prefix ??= "\n";

                    continue;
                }

                echo $prefix . \json_encode((array)$item, \JSON_THROW_ON_ERROR | $this->encodingOptions);
                \ob_flush();
                \flush();
                $prefix ??= "\n";
            }
        }, $status, $headers);

        if (!$this->headers->has('Content-Type')) {
            $this->headers->set('Content-Type', 'application/json');
        }
    }
}
