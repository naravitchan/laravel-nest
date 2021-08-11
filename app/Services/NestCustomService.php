<?php
namespace App\Services;

use Predis\PubSub\Consumer;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Collection;

class NestCustomService
{
    protected $redis;
    protected $pubsubRedis;

    public function __construct()
    {
        $this->redis = Redis::connection('default');
        $this->pubsubRedis = Redis::connection('pubsub');
    }

    /*---------------------------------------------------------------------*
        PUBLIC METHODS
      *---------------------------------------------------------------------*/
    public function send($pattern, $data = null)
    {
        // Build the payload object from the params
        $payload = $this->newPayload($pattern, $data);
        // Make a call to NestJS with the payload &
        // return the response.
        return $this->callNestMicroservice($payload);
    }

    /*---------------------------------------------------------------------*
        INTERNAL METHODS
      *---------------------------------------------------------------------*/
    /**
    * Create new UUID
    *
    * @return string
    */
    protected function newUuid()
    {
        return Str::uuid()->toString();
    }

    /**
    * Create new collection
    *
    * @return Collection
    */
    protected function newCollection()
    {
        return collect();
    }

    /**
    * Create new payload array
    *
    * @param string $pattern
    * @param mixed $data
    * @return array
    */
    protected function newPayload($pattern, $data)
    {
        return [
            'id' => $this->newUuid(),
            'pattern' => $pattern,
            'data' => $data,
        ];
    }

    /**
    * Make request to microservice
    *
    * @param array $payload
    * @return Collection
    */
    protected function callNestMicroservice($payload)
    {
        $uuid = $payload['id'];
        $pattern = $payload['pattern'];

        \Log::info('sub');
        $loop = $this->pubsubRedis
            ->pubSubLoop(['subscribe' => "{$pattern}.reply"]);
        \Log::info('finished sub: ' . "{$pattern}.reply");

        // Send payload across the request channel
        \Log::info('pub');
        $this->redis
          ->publish("{$pattern}", json_encode($payload));
        \Log::info('finished pub: ' . "{$pattern}");
        // Create a collection to store response(s); there could be multiple!
        // (e.g., if NestJS returns an observable)
        $result = $this->newCollection();
        // Loop through the response object(s), pushing the returned vals into
        // the collection.  If isDisposed is true, break out of the loop.
        foreach ($loop as $msg) {
            if ($msg->kind === 'message') {
                $res = json_decode($msg->payload);
                if ($res->id === $uuid) {
                    $result->push($res->response);
                    if (property_exists($res, 'isDisposed') && $res->isDisposed) {
                        $loop->stop();
                    }
                }
            }
        }
        return $result; // return the collection
    }
}

// use App\Services\NestCustomService;
// $a = new NestCustomService;
// $r = $a->send(['cmd' => 'greeting'], 'Karla');
// $r = $a->send(['cmd' => 'observable'], 'Karla');

// use Illuminate\Support\Facades\Redis;
// $pubsubRedis = Redis::connection('pubsub');
// $loop = $pubsubRedis->pubSubLoop(['subscribe' => "seagame_s"]);
