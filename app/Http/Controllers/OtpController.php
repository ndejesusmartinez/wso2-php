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
        $validateUser = $this->validateUserExists($request->input('usuario'));
        $idUser = $validateUser->original['Resources']['0']['id'];
        $email = self::getEmailByUser($idUser);
        $status = $validateUser->getStatusCode();

        try {
            if ($status == '200' && $email->original['status']){
                $otp = (new Otp)->generate($request->input('usuario'), 'numeric', 6, 5);
                if($otp->status){
                    $email = self::sendEmailOtp($otp->token, $email->original['email']['value']);
                    dd($email);
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
            } else if ($status == '404') {
                return response()->json(['error' => 'El usuario no fue encontrado'], 404);
            } else if ($status == '500') {
                return response()->json(['error' => $validateUser], 500);
            }
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }

    }

    public function validateUserExists($user)
    {
        $accessToken = $this->getManagementAccessToken();
        $client = new \GuzzleHttp\Client();

        try {
            $response = $client->get('https://localhost:9443/wso2/scim/Users?filter=userName eq "' . $user . '"', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'verify' => false,
            ]);

            $responseBody = $response->getBody()->getContents();
            $userData = json_decode($responseBody, true);

            if ($userData['totalResults'] > 0) {
                return response()->json($userData, 200);
            }

        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $statusText = $e->getResponse()->getReasonPhrase();
                $errorBody = $e->getResponse()->getBody()->getContents();

                return response()->json([
                    'error' => $statusText,
                    'code' => $statusCode,
                    'details' => json_decode($errorBody, true),
                ], $statusCode);
            } else {
                return response()->json(['error' => $e->getMessage()], 500);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Usuario Inexistente'], 404);
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
            $response = $client->post(env('WSO2_TOKEN_URL'), [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => env('WSO2_CLIENT_ID'),
                    'client_secret' => env('WSO2_CLIENT_SECRET'),
                ],
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

    private function getEmailByUser($id)
    {
        try {
            $client = new Client();
            $response = $client->request('GET', 'https://localhost:9443/scim2/Users/'."$id", [
                'headers' => [
                    'Authorization' => 'Basic YWRtaW46YWRtaW4='
                ],
                'verify' => false,
            ]);
            $dataUser = json_decode($response->getBody(), true);
            if(!empty($dataUser['emails'][0])){
                return response()->json([
                    'status' => true,
                    'email' => $dataUser['emails'][0]
                ], 200);
            }else{
                return response()->json([
                    'status' => false,
                    'msg' => 'Usuario no encontrado, favor contactar a soporte'
                ],404);
            }

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'msg' => $th->getMessage()
            ],500);
        }
    }
}
