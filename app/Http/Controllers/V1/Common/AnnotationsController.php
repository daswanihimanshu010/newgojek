<?php

namespace App\Http\Controllers\V1\Common;

use App\Http\Controllers\Controller;
use SwaggerLume\Http\Controllers\SwaggerLumeController;
use App\Models\Common\Setting;
use App\Models\Common\AdminService;
use App\Models\Common\CompanyCountry;
use App\Services\SendPushNotification;
use App\Models\Common\CompanyCity;
use App\Models\Common\Company;
use App\Models\Common\Country;
use App\Models\Common\State;
use App\Models\Common\City;
use App\Models\Common\Menu;
use App\Models\Common\CmsPage;
use App\Models\Common\Rating;
use App\Models\Common\AuthLog;
use App\Models\Common\UserWallet;
use App\Models\Common\ProviderWallet;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Request;
use App\Models\Common\FleetWallet;
use App\Models\Common\Chat;
use App\Helpers\Helper;
use Carbon\Carbon;
use Auth;

class AnnotationsController extends Controller
{
    /* *********************************************************************
    *   USER LOGIN
    **********************************************************************/

    /**
     * @OA\Post(
     *     path="/user/login",
     *     operationId="/user/login",
     *     tags={"Authentication"},
     *     description="User Login",
     *     @OA\RequestBody(
     *         description="User Login",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\JsonContent(ref="#/components/schemas/UserLoginInput")
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns settings for the application",
     *         @OA\JsonContent(ref="#/components/schemas/NewLogin")
     *     ),
     *     @OA\Response(
     *         response="422",
     *         description="Error: Unprocessable entity. When required parameters were not supplied.",
     *         @OA\JsonContent()
     *     ),
     * )
     */
    /**
     * @OA\Schema(schema="NewLogin", required={"salt_key","password"})
     * 
     */ 
    /**
     *  @OA\Schema(
     *   schema="UserLoginInput",
     *   type="object",
     *   allOf={
     *       @OA\Schema(ref="#/components/schemas/NewLogin"),
     *       @OA\Schema(
     *           @OA\Property(property="email", type="string"),
     *           @OA\Property(property="country_code", type="integer"),
     *           @OA\Property(property="mobile", type="integer"),
     *           @OA\Property(property="password", type="string"),
     *           @OA\Property(property="salt_key", type="string")
     *       )
     *   }
     * )
     */

     /* *********************************************************************
    *   USER SIGNUP
    **********************************************************************/

    /**
     * @OA\Post(
     *     path="/user/signup",
     *     operationId="/user/signup",
     *     tags={"Authentication"},
     *     description="User Signup",
     *     @OA\RequestBody(
     *         description="User Signup",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\JsonContent(ref="#/components/schemas/UserSignupInput")
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns settings for the application",
     *         @OA\JsonContent(ref="#/components/schemas/NewSignup")
     *     ),
     *     @OA\Response(
     *         response="422",
     *         description="Error: Unprocessable entity. When required parameters were not supplied.",
     *         @OA\JsonContent()
     *     ),
     * )
     */
    /**
     * @OA\Schema(schema="NewSignup", required={"salt_key","first_name","last_name","mobile","country_code","email"})
     * 
     */ 
    /**
     *  @OA\Schema(
     *   schema="UserSignupInput",
     *   type="object",
     *   allOf={
     *       @OA\Schema(ref="#/components/schemas/NewSignup"),
     *       @OA\Schema(
     *           @OA\Property(property="first_name", type="string"),
     *           @OA\Property(property="last_name", type="string"),
     *           @OA\Property(property="mobile", type="integer"),
     *           @OA\Property(property="country_code", type="integer"),
     *           @OA\Property(property="email", type="string"),
     *           @OA\Property(property="gender", type="string"),
     *           @OA\Property(property="device_type", type="string"),
     *           @OA\Property(property="device_token", type="string"),
     *           @OA\Property(property="login_by", type="string"),
     *           @OA\Property(property="password", type="string"),
     *           @OA\Property(property="country_id", type="integer"),
     *           @OA\Property(property="city_id", type="integer"),
     *           @OA\Property(property="picture", type="string"),
     *           @OA\Property(property="social_unique_id", type="string"),
     *           @OA\Property(property="referral_code", type="string"),
     *           @OA\Property(property="salt_key", type="string")
     *       )
     *   }
     * )
     */
    /* *********************************************************************
    *   USER FORGOT PASSWORD
    **********************************************************************/

    /**
     * @OA\Post(
     *     path="/user/forgot/otp",
     *     operationId="/user/forgot/otp",
     *     tags={"Authentication"},
     *     description="User Forgot Password",
     *     @OA\RequestBody(
     *         description="User Forgot Password",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\JsonContent(ref="#/components/schemas/UserForgotInput")
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns settings for the application",
     *         @OA\JsonContent(ref="#/components/schemas/NewForgot")
     *     ),
     *     @OA\Response(
     *         response="422",
     *         description="Error: Unprocessable entity. When required parameters were not supplied.",
     *         @OA\JsonContent()
     *     ),
     * )
     */
    /**
     * @OA\Schema(schema="NewForgot", required={"salt_key","account_type"})
     * 
     */ 
    /**
     *  @OA\Schema(
     *   schema="UserForgotInput",
     *   type="object",
     *   allOf={
     *       @OA\Schema(ref="#/components/schemas/NewForgot"),
     *       @OA\Schema(
     *           @OA\Property(property="email", type="string"),
     *           @OA\Property(property="country_code", type="integer"),
     *           @OA\Property(property="mobile", type="integer"),
     *           @OA\Property(property="account_type", type="string",description="mobile / email"),
     *           @OA\Property(property="salt_key", type="string")
     *       )
     *   }
     * )
     */
    /* *********************************************************************
    *   USER RESET PASSWORD
    **********************************************************************/

    /**
     * @OA\Post(
     *     path="/user/reset/otp",
     *     operationId="/user/reset/otp",
     *     tags={"Authentication"},
     *     description="User Reset Password",
     *     @OA\RequestBody(
     *         description="User Reset Password",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\JsonContent(ref="#/components/schemas/UserResetInput")
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns settings for the application",
     *         @OA\JsonContent(ref="#/components/schemas/NewReset")
     *     ),
     *     @OA\Response(
     *         response="422",
     *         description="Error: Unprocessable entity. When required parameters were not supplied.",
     *         @OA\JsonContent()
     *     ),
     * )
     */
    /**
     * @OA\Schema(schema="NewReset", required={"salt_key","account_type"})
     * 
     */ 
    /**
     *  @OA\Schema(
     *   schema="UserResetInput",
     *   type="object",
     *   allOf={
     *       @OA\Schema(ref="#/components/schemas/NewReset"),
     *       @OA\Schema(
     *           @OA\Property(property="username", type="string",description="if account_type is mobile, username is mobile number. If account_type is email, username is email id."),
     *           @OA\Property(property="country_code", type="integer"),
     *           @OA\Property(property="otp", type="string"),
     *           @OA\Property(property="password", type="string"),
     *           @OA\Property(property="password_confirmation", type="string"),
     *           @OA\Property(property="account_type", type="string",description="mobile / email. If mobile, send country_code also"),
     *           @OA\Property(property="salt_key", type="string")
     *       )
     *   }
     * )
     */

     /* *********************************************************************
    *   USER VERIFY 
    **********************************************************************/

    /**
     * @OA\Post(
     *     path="/user/verify",
     *     operationId="/user/verify",
     *     tags={"Authentication"},
     *     description="User Verify",
     *     @OA\RequestBody(
     *         description="User Verify",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\JsonContent(ref="#/components/schemas/UserVerifyInput")
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns settings for the application",
     *         @OA\JsonContent(ref="#/components/schemas/NewVerify")
     *     ),
     *     @OA\Response(
     *         response="422",
     *         description="Error: Unprocessable entity. When required parameters were not supplied.",
     *         @OA\JsonContent()
     *     ),
     * )
     */
    /**
     * @OA\Schema(schema="NewVerify", required={"salt_key","account_type"})
     * 
     */ 
    /**
     *  @OA\Schema(
     *   schema="UserVerifyInput",
     *   type="object",
     *   allOf={
     *       @OA\Schema(ref="#/components/schemas/NewVerify"),
     *       @OA\Schema(
     *           @OA\Property(property="country_code", type="integer"),
     *           @OA\Property(property="email", type="string"),
     *           @OA\Property(property="mobile", type="integer"),
     *           @OA\Property(property="salt_key", type="string")
     *       )
     *   }
     * )
     */

     /* *********************************************************************
    *   USER REFRESH 
    **********************************************************************/

    /**
     * @OA\Post(
     *     path="/user/refresh",
     *     operationId="/user/refresh",
     *     tags={"Authentication"},
     *     description="User Refresh",
     *     @OA\RequestBody(
     *         description="User Refresh",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\JsonContent(ref="#/components/schemas/UserRefreshInput")
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns settings for the application",
     *         @OA\JsonContent(ref="#/components/schemas/NewRefresh")
     *     ),
     *     @OA\Response(
     *         response="422",
     *         description="Error: Unprocessable entity. When required parameters were not supplied.",
     *         @OA\JsonContent()
     *     ),
     * )
     */
    /**
     * @OA\Schema(schema="NewRefresh", required={"salt_key","account_type"})
     * 
     */ 
    /**
     *  @OA\Schema(
     *   schema="UserRefreshInput",
     *   type="object",
     *   allOf={
     *       @OA\Schema(ref="#/components/schemas/NewRefresh"),
     *       @OA\Schema(
     *           @OA\Property(property="Authorization", type="integer")
     *       )
     *   }
     * )
     */

    /* *********************************************************************
    *   PROVIDER LOGIN
    **********************************************************************/
     
    /**
     * @OA\Post(
     *     path="/provider/login",
     *     operationId="/provider/login",
     *     tags={"Authentication"},
     *     description="Provider Login",
     *     @OA\RequestBody(
     *         description="Provider Login",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\JsonContent(ref="#/components/schemas/ProviderLoginInput")
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns settings for the application",
     *         @OA\JsonContent(ref="#/components/schemas/NewProviderLogin")
     *     ),
     *     @OA\Response(
     *         response="422",
     *         description="Error: Unprocessable entity. When required parameters were not supplied.",
     *         @OA\JsonContent()
     *     ),
     * )
     */
    /**
     * @OA\Schema(schema="NewProviderLogin", required={"salt_key","password"})
     * 
     */ 
    /**
     *  @OA\Schema(
     *   schema="ProviderLoginInput",
     *   type="object",
     *   allOf={
     *       @OA\Schema(ref="#/components/schemas/NewProviderLogin"),
     *       @OA\Schema(
     *           @OA\Property(property="email", type="string"),
     *           @OA\Property(property="country_code", type="integer"),
     *           @OA\Property(property="mobile", type="integer"),
     *           @OA\Property(property="password", type="string"),
     *           @OA\Property(property="salt_key", type="string")
     *       )
     *   }
     * )
     */


     /* *********************************************************************
    *   PROVIDER SIGNUP
    **********************************************************************/

    /**
     * @OA\Post(
     *     path="/provider/signup",
     *     operationId="/provider/signup",
     *     tags={"Authentication"},
     *     description="Provider Signup",
     *     @OA\RequestBody(
     *         description="Provider Signup",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\JsonContent(ref="#/components/schemas/ProviderSignupInput")
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns settings for the application",
     *         @OA\JsonContent(ref="#/components/schemas/NewProviderSignup")
     *     ),
     *     @OA\Response(
     *         response="422",
     *         description="Error: Unprocessable entity. When required parameters were not supplied.",
     *         @OA\JsonContent()
     *     ),
     * )
     */
    /**
     * @OA\Schema(schema="NewProviderSignup", required={"salt_key","first_name","last_name","mobile","country_code","email"})
     * 
     */ 
    /**
     *  @OA\Schema(
     *   schema="ProviderSignupInput",
     *   type="object",
     *   allOf={
     *       @OA\Schema(ref="#/components/schemas/NewProviderSignup"),
     *       @OA\Schema(
     *           @OA\Property(property="first_name", type="string"),
     *           @OA\Property(property="last_name", type="string"),
     *           @OA\Property(property="mobile", type="integer"),
     *           @OA\Property(property="country_code", type="integer"),
     *           @OA\Property(property="email", type="string"),
     *           @OA\Property(property="gender", type="string"),
     *           @OA\Property(property="device_type", type="string"),
     *           @OA\Property(property="device_token", type="string"),
     *           @OA\Property(property="login_by", type="string"),
     *           @OA\Property(property="password", type="string"),
     *           @OA\Property(property="country_id", type="integer"),
     *           @OA\Property(property="city_id", type="integer"),
     *           @OA\Property(property="picture", type="string"),
     *           @OA\Property(property="social_unique_id", type="string"),
     *           @OA\Property(property="salt_key", type="string")
     *       )
     *   }
     * )
     */
    /* *********************************************************************
    *   PROVIDER FORGOT PASSWORD
    **********************************************************************/

    /**
     * @OA\Post(
     *     path="/provider/forgot/otp",
     *     operationId="/provider/forgot/otp",
     *     tags={"Authentication"},
     *     description="Provider Forgot Password",
     *     @OA\RequestBody(
     *         description="Provider Forgot Password",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\JsonContent(ref="#/components/schemas/ProviderForgotInput")
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns settings for the application",
     *         @OA\JsonContent(ref="#/components/schemas/NewProviderForgot")
     *     ),
     *     @OA\Response(
     *         response="422",
     *         description="Error: Unprocessable entity. When required parameters were not supplied.",
     *         @OA\JsonContent()
     *     ),
     * )
     */
    /**
     * @OA\Schema(schema="NewProviderForgot", required={"salt_key","account_type"})
     * 
     */ 
    /**
     *  @OA\Schema(
     *   schema="ProviderForgotInput",
     *   type="object",
     *   allOf={
     *       @OA\Schema(ref="#/components/schemas/NewProviderForgot"),
     *       @OA\Schema(
     *           @OA\Property(property="email", type="string"),
     *           @OA\Property(property="country_code", type="integer"),
     *           @OA\Property(property="mobile", type="integer"),
     *           @OA\Property(property="account_type", type="string",description="mobile / email"),
     *           @OA\Property(property="salt_key", type="string")
     *       )
     *   }
     * )
     */

    /* *********************************************************************
    *   PROVIDER RESET PASSWORD
    **********************************************************************/

    /**
     * @OA\Post(
     *     path="/provider/reset/otp",
     *     operationId="/provider/reset/otp",
     *     tags={"Authentication"},
     *     description="Provider Reset Password",
     *     @OA\RequestBody(
     *         description="Provider Reset Password",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\JsonContent(ref="#/components/schemas/ProviderResetInput")
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns settings for the application",
     *         @OA\JsonContent(ref="#/components/schemas/ProviderReset")
     *     ),
     *     @OA\Response(
     *         response="422",
     *         description="Error: Unprocessable entity. When required parameters were not supplied.",
     *         @OA\JsonContent()
     *     ),
     * )
     */
    /**
     * @OA\Schema(schema="ProviderReset", required={"salt_key","account_type"})
     * 
     */ 
    /**
     *  @OA\Schema(
     *   schema="ProviderResetInput",
     *   type="object",
     *   allOf={
     *       @OA\Schema(ref="#/components/schemas/ProviderReset"),
     *       @OA\Schema(
     *           @OA\Property(property="username", type="string",description="if account_type is mobile, username is mobile number. If account_type is email, username is email id."),
     *           @OA\Property(property="country_code", type="integer"),
     *           @OA\Property(property="otp", type="string"),
     *           @OA\Property(property="password", type="string"),
     *           @OA\Property(property="password_confirmation", type="string"),
     *           @OA\Property(property="account_type", type="string",description="mobile / email. If mobile, send country_code also"),
     *           @OA\Property(property="salt_key", type="string")
     *       )
     *   }
     * )
     */


      /* *********************************************************************
    *   ADMIN LOGIN
    **********************************************************************/

    /**
     * @OA\Post(
     *     path="/admin/login",
     *     operationId="/admin/login",
     *     tags={"Authentication"},
     *     description="Admin Login",
     *     @OA\RequestBody(
     *         description="Admin Login",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\JsonContent(ref="#/components/schemas/AdminLoginInput")
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns settings for the application",
     *         @OA\JsonContent(ref="#/components/schemas/NewAdminLogin")
     *     ),
     *     @OA\Response(
     *         response="422",
     *         description="Error: Unprocessable entity. When required parameters were not supplied.",
     *         @OA\JsonContent()
     *     ),
     * )
     */
    /**
     * @OA\Schema(schema="NewAdminLogin", required={"email","salt_key","password"})
     * 
     */ 
    /**
     *  @OA\Schema(
     *   schema="AdminLoginInput",
     *   type="object",
     *   allOf={
     *       @OA\Schema(ref="#/components/schemas/NewAdminLogin"),
     *       @OA\Schema(
     *           @OA\Property(property="email", type="string"),
     *           @OA\Property(property="password", type="string"),
     *           @OA\Property(property="salt_key", type="string"),
     *           @OA\Property(property="role", type="string"),
     *       )
     *   }
     * )
     */
}