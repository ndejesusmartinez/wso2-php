<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Ichtrojan\Otp\Otp;
use GuzzleHttp\Client;
use PHPMailer\PHPMailer\PHPMailer;
use Exception;

class OtpController extends Controller
{
    public function generateOtp(Request $request)
    {
        $validateUser = $this->getInfoUser($request->input('usuario'));
        try {
            if($validateUser){
                $otp = (new Otp)->generate($request->input('usuario'), 'numeric', 6, 5);
                if($otp->status){
                    $email = self::sendEmailOtp($otp->token, $validateUser[0]['email']);
                    if($email['status'] == "success"){
                        return response()->json([
                            'status' => true,
                            'message' => $otp->message
                        ], 200);
                    }else{
                        return response()->json([
                            'ERROR' => $email['msg']
                        ], 500); 
                    }
                }
            }
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }

    }

    public function getInfoUser($user)
    {
        $accessToken = $this->getManagementAccessToken();
        $client = new \GuzzleHttp\Client();

        try {
            $response = $client->get(env('KEYCLOAK_BASE_URL'). "/". env('KEYCLOAK_REALM').'/realms/'.env('KEYCLOAK_REALM').'/users', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'query' => [
                    'username' => $user
                ],
                'verify' => false,
            ]);

            $userData = json_decode($response->getBody(), true);

            if($userData) {
                return $userData;
            } else {
                return response()->json(['error' => 'No se encuentra el user ID'], 404);
            }

        } catch (RequestException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function validateCodeOtp(Request $request)
    {
        try {
            $validateotp = (new Otp)->validate($request->input('usuario'), $request->input('codeOtp'));

            return $validateotp;

        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    private function getManagementAccessToken()
    {
        $client = new Client();

        try {
            $url = env('KEYCLOAK_BASE_URL').'/realms/'.env('KEYCLOAK_REALM').'/protocol/openid-connect/token';

            $body = [
                'grant_type' => 'client_credentials',
                'client_id' => env('KEYCLOAK_CLIENT_ID'),
                'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
            ];

            $response = $client->post($url, [
                'headers' => [
                    'accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => $body,
                'verify' => false,
            ]);
            $data = json_decode($response->getBody(), true);

            return $data['access_token'];

        } catch (\Exception $e) {
            throw new \Exception('Error al obtener el token de acceso: ' . $e->getMessage());
        }
    }

    private function sendEmailOtp($codeOtp, $email)
    {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = env('AWS_REGION');
            $mail->SMTPAuth   = true;
            $mail->Username   = env('AWS_KEY');
            $mail->Password   = env('AWS_SECRET');
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom(env('AWS_FROM'), 'Equipo Domina Entrega Total');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Código OTP';
            $body = view('OTP.otp', ['otp' => $codeOtp])->render();
            $mail->Body    = $body;
            $mail->send();

        } catch (Exception  $e) {
            return [
                'status' => 'ERROR',
                'msg' => $e->getMessage(),
            ];
        } catch (Exception $e) {
            return [
                'status' => 'ERROR',
                'msg' => $e->getMessage(),
            ];
        }
        return [
            'status' => 'success',
            'msg' => 'Enviado Correctamente'
        ];
    }
}
