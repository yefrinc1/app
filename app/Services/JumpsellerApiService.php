<?php

namespace App\Services;

use GuzzleHttp\Client;

class JumpsellerApiService
{
    protected $client;
    protected $ofertaCategoriaId;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.jumpseller.com/v1/',
            'auth' => [env('JUMPSELLER_API_LOGIN'), env('JUMPSELLER_API_TOKEN')],
        ]);
        $this->ofertaCategoriaId = env('JUMPSELLER_OFERTAS_CATEGORY_ID');
    }

    public function getProductoAll($limit = 50, $page = 1)
    {
        $query = [
            'limit' => $limit,
            'page' => $page,
        ];

        $response = $this->client->get("products.json",  [
            'query' => $query
        ]);
        return json_decode($response->getBody(), true);
    }

    public function getProudctoAllCount()
    {
        $response = $this->client->get("products/count.json");
        return json_decode($response->getBody(), true);
    }

    public function getProductoId($productId)
    {
        $response = $this->client->get("products/{$productId}.json");
        return json_decode($response->getBody(), true);
    }

    public function getProductosOferta($limit = 50, $page = 1)
    {
        $query = [
            'limit' => $limit,
            'page' => $page,
        ];

        $response = $this->client->get("products/category/{$this->ofertaCategoriaId}.json", [
            'query' => $query
        ]);
        return json_decode($response->getBody(), true);
    }

    public function getProductosOfertaCount()
    {
        $response = $this->client->get("products/category/{$this->ofertaCategoriaId}/count.json");
        return json_decode($response->getBody(), true);
    }

    public function updateProducto($productId, array $data)
    {
        $response = $this->client->put("products/{$productId}.json", [
            'json' => $data
        ]);
        return json_decode($response->getBody(), true);
    }

    public function deleteProductoOptionValue($productId, $optionId, $valueId)
    {
        $response = $this->client->delete("products/{$productId}/options/{$optionId}/values/{$valueId}.json");
        return json_decode($response->getBody(), true);
    }
}