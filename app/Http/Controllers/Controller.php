<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{

    /**
     * @OA\Info(
     *     description="GoX API Documentation.",
     *     version="1.0.0",
     *     title="GoX",
     *     @OA\Contact(
     *         email="test@demo.com"
     *     )
     * )
     */
    /**
     *  @OA\Server(
     *      url="/api/v1",
     *      description="Swagger api url"
     *  )
     */
    /**
     *  @OA\Tag(
     *     name="Authentication",
     *     description="Admin, User and Provider Authentication APIs"
     * )
     * @OA\Tag(
     *     name="Common",
     *     description="Common APIs"
     * )
     * @OA\Tag(
     *     name="Transport",
     *     description="Transport APIs"
     * )
     * @OA\Tag(
     *     name="Order",
     *     description="Order APIs",
     * )
     * @OA\Tag(
     *     name="Service",
     *     description="Service APIs"
     * )
     */
}
