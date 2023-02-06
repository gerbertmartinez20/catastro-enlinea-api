<?php 

    namespace App\Http\Controllers;
        
    use Illuminate\Http\Request;

    class LoginController extends Controller{
    
        public function login(Request $request){

            $result = app('db')->select("   SELECT *
                                            FROM RH_EMPLEADOS
                                            WHERE USUARIO = UPPER('$request->usuario')
                                            AND UPPER(DESENCRIPTAR(PASS)) = UPPER('$request->password')");

            if(!$result){

                $response = [
                    "status" => 100,
                    "message" => "Usuario o contraseña incorrectos"
                ];

                return response()->json($response);

            }

            $empleado = $result[0];

            $response = [
                "status" => 200,
                "data" => [
                    "nit" => $empleado->nit,
                    "nombre" => $empleado->nombre . ' ' . $empleado->apellido,
                    "jefe" => $empleado->jefe
                ]
            ];

            return response()->json($response);

        }

        public function public_login(Request $request){

            try {

                $clave = md5($request->password);
                
                $result = app('db')->select("   SELECT ID, NOMBRES, APELLIDOS, EMAIL, ESTATUS
                                                FROM CATASTRO.SERV_USUARIO
                                                WHERE UPPER(EMAIL) = UPPER('$request->email')
                                                AND PASSWORD = '$clave'
                                                AND ESTATUS = 'A'");

                if ($result) {
                    
                    // Validar si la cuenta ha sido rechazada
                    if ($result[0]->estatus == 'R') {
                        
                        $response = [
                            "status" => 100,
                            "message" => "Su solicitud para habilitación de usuario ha sido rechazada, por favor ponerse en contacto con el administrador."
                        ];
    
                        return response()->json($response);

                    }

                    if (!$result[0]->estatus) {
                        
                        $response = [
                            "status" => 100,
                            "message" => "Su solicitud para habilitación de usuario aún esta pendiente de aprobación."
                        ];
    
                        return response()->json($response);

                    }

                    $response = [
                        "status" => 200,
                        "data" => $result[0]
                    ];

                    return response()->json($response);

                }

                // Enviar mensaje de credenciales incorrectas
                $response = [
                    "status" => 100,
                    "message" => "Por favor verique los datos ingresados.  Correo electrónico o contraseña incorrectos."
                ];

                return response()->json($response);

            } catch (\Throwable $th) {
                
                return response()->json($th->getMessage());

            }
            
            return response()->json($result);

        }

    }

?>