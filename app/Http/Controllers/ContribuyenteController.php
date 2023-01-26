<?php

    namespace App\Http\Controllers;

    use Illuminate\Http\Request;

    use App\Usuario;
    use App\MatriculaUsuario;
    use App\SolicitudUsuario;
    use App\MatriculaSolicitud;
    use Barryvdh\DomPDF\Facade\Pdf;


    require base_path() . '/vendor/PHPMailer_old/PHPMailerAutoload.php';

    use App\Jobs\MailJob;

    use Illuminate\Support\Facades\Mail;

    class ContribuyenteController extends Controller{

        public function obtener_matriculas(Request $request){

            $matriculas = app('db')->select("   SELECT
                                                    ID,
                                                    MATRICULA
                                                FROM CATASTRO.SERV_MATRICULA_USUARIO
                                                WHERE USUARIO_ID = $request->id");

            return response()->json($matriculas);

        }

        public function matricula_interlocutor(Request $request){

            $interlocutor = app('db')->select(" SELECT *
                                                FROM CIERRES_IUSI_SAP.SALDOTRIMESTRE
                                                WHERE MATRICULA = '$request->matricula'");

            if(!$interlocutor){

                $response = [
                    "status" => 100
                ];

                return response()->json($response);

            }

            $response = [
                "status" => 200,
                "interlocutor" => $interlocutor[0]->interlocutor
            ];

            return response()->json($response);

        }

        public function datos_personales(Request $request){

            $usuario = Usuario::find($request->id);

            return response()->json($usuario);

        }

        public function actualizar_perfil(Request $request){

            $usuario = Usuario::find($request->id);

            $usuario->nombres = $request->nombres;
            $usuario->apellidos = $request->apellidos;
            $usuario->sexo = $request->sexo;
            $usuario->email = $request->email;
            $usuario->direccion = $request->direccion;
            $usuario->telefono = $request->telefono;

            $usuario->save();

            return response()->json($request);

        }

        public function matriculas_registradas(Request $request){

            $matriculas = app('db')->select("   SELECT
                                                    ID,
                                                    MATRICULA,
                                                    SOLICITUD_ID,
                                                    TO_CHAR(UPDATED_AT, 'DD/MM/YYY HH24:MI:SS') AS CREATED_AT,
                                                    ESTADO
                                                FROM CATASTRO.SERV_MATRICULA_SOLICITUD
                                                WHERE SOLICITUD_ID IN (
                                                    SELECT ID
                                                    FROM CATASTRO.SERV_SOLICITUD_USUARIO
                                                    WHERE USUARIO_ID = $request->id
                                                    AND TIPO_SOLICITUD_ID IN (1,2)
                                                )
                                                ORDER BY ID DESC");

            return response()->json($matriculas);

        }

        public function roles_registrados(Request $request){

            $roles = app('db')->select("SELECT
                                            T1.*,
                                            T2.NOMBRE
                                        FROM CATASTRO.SERV_USUARIO_TIPO T1
                                        INNER JOIN CATASTRO.SERV_TIPO_USUARIO T2
                                        ON T1.TIPO_USUARIO_ID = T2.ID
                                        WHERE T1.USUARIO_ID = $request->usuario_id");

            foreach ($roles as &$rol) {

                $rol->estado = $rol->estatus === 'A' ? 'Aceptado' : $rol->estatus == 'P' ? 'Pendiente' : 'Rechazado';

                $rol->color = $rol->estatus === 'A' ? 'success' : $rol->estatus == 'P' ? 'warning' : 'error';

            }

            // Obtener los role que aún no ha solicitado
            $roles_faltantes = app('db')->select("  SELECT *
                                                    FROM CATASTRO.SERV_TIPO_USUARIO
                                                    WHERE ID NOT IN (
                                                        SELECT
                                                            TIPO_USUARIO_ID
                                                        FROM CATASTRO.SERV_USUARIO_TIPO
                                                        WHERE USUARIO_ID = $request->usuario_id
                                                    )
                                                    ORDER BY ID ASC");

            $response = [
                "roles" => $roles,
                "roles_faltantes" => $roles_faltantes
            ];

            return response()->json($response);

        }

        public function ingresar_solicitud(Request $request){

            $usuario = Usuario::find($request->usuario_id);

            $solicitud = new SolicitudUsuario();

            $solicitud->usuario_id = $request->usuario_id;
            $solicitud->estatus = 'P';
            $solicitud->tipo_solicitud_id = 2;
            $solicitud->save();

            // Registrar matrícula
            $matricula = new MatriculaSolicitud();
            $matricula->matricula = $request->matricula;
            $matricula->estado = 'P';
            $matricula->solicitud_id = $solicitud->id;
            $matricula->save();

            // Enviar correos de notificación
            $datos_correo = [
                // Correo para el solicitante
                [
                    "solicitud" => $solicitud->id,
                    "email" => $usuario->email,
                    "body" =>   '<p>Estimado(a): ' . $usuario->nombres . ' ' . $usuario->apellidos . '</p>' .
                                '<p>Su gestión para la habilitación de una nueva matrícula ha sido ingresada exitosamente.</p>' .
                                '<p>Cuando su gestión haya sido aprobada se le estará notificando por esta vía</p>' .
                                '<p>Atentamente, </p>' .
                                '<p><strong>Dirección de Catastro y Administración del IUSI</strong></p>',
                                '<p><strong>Teléfono: 2285-8600 / 2285-8611</strong></p>',
                    "subject" => 'Solicitud para agregar matrícula No. ' . $solicitud->id
                ],
                // Correo para el administrador
                [
                    "solicitud" => $solicitud->id,
                    "email" => 'caherrera@muniguate.com',
                    "body" => '<p>Se ha generado una nueva solicitud No. ' . $solicitud->id . '</p><p>Por favor ingrese a la siguiente dirección para verificar la información</p><p> <a href="https://udicat.muniguate.com/apps/catastro-enlinea-app/#/admin">Ingresar</a> </p>',
                    "subject" => 'Solicitud de Creación de Usuario No. ' . $solicitud->id
                ]
            ];

            \Queue::push(new MailJob($datos_correo));

            return response()->json($request);

        }

        public function roles_faltantes(Request $request){

            return response()->json($request);

        }

        public function ingresar_solicitud_rol(Request $request){

            $solicitud = new SolicitudUsuario();

            $solicitud->usuario_id = $request->usuario_id;
            $solicitud->estatus = 'P';
            $solicitud->tipo_solicitud_id = 3;
            $solicitud->save();

            $rol = (object) $request->rol;

            $result = app('db')->table('SERV_USUARIO_TIPO')->insert([
                "usuario_id" => $request->usuario_id,
                "tipo_usuario_id" => $rol->id,
                "estatus" => 'P',
                "created_at" => date('Y-m-d H:i:s'),
                "updated_at" => date('Y-m-d H:i:s'),
                "solicitud_id" => $solicitud->id
            ]);

            // Actualizar la información del usuario
            $usuario = Usuario::find($request->usuario_id);

            switch ($rol->id) {
                case 3:
                    $usuario->representacion_legal = $rol->representacion_legal;
                    break;
                case 4:
                    $usuario->carne_valuador = $rol->carne_valuador;
                    break;
                case 5:
                    $usuario->carne_abogado = $rol->carne_abogado;
                    break;
                default:
                    break;
            }

            $usuario->save();

            // Notificar por medio de correo
            $datos_correo = [
                // Correo para el solicitante
                [
                    "solicitud" => $solicitud->id,
                    "email" => $usuario->email,
                    "body" =>   '<p>Estimado(a): ' . $usuario->nombres . ' ' . $usuario->apellidos . '</p>' .
                                '<p>Su gestión para la habilitación de un nuevo rol ha sido ingresada exitosamente.</p>' .
                                '<p>Cuando su gestión haya sido aprobada se le estará notificando por esta vía</p>' .
                                '<p>Atentamente, </p>' .
                                '<p><strong>Dirección de Catastro y Administración del IUSI</strong></p>',
                                '<p><strong>Teléfono: 2285-8600 / 2285-8611</strong></p>',
                    "subject" => 'Solicitud para agregar matrícula No. ' . $solicitud->id
                ],
                // Correo para el administrador
                [
                    "solicitud" => $solicitud->id,
                    "email" => 'caherrera@muniguate.com',
                    "body" => '<p>Se ha generado una nueva solicitud No. ' . $solicitud->id . '</p><p>Por favor ingrese a la siguiente dirección para verificar la información</p><p> <a href="https://udicat.muniguate.com/apps/catastro-enlinea-app/#/admin">Ingresar</a> </p>',
                    "subject" => 'Solicitud de Creación de Usuario No. ' . $solicitud->id
                ]
            ];

            \Queue::push(new MailJob($datos_correo));

            return response()->json($usuario);

        }

 /* este */
        public function estado_cuenta(Request $request){

            $interlocutor = app('db')->select(" SELECT PREDIO
                                                FROM CIERRES_IUSI_SAP.SALDOTRIMESTRE
                                                WHERE MATRICULA = '$request->matricula'");
            if(!$interlocutor){

                $response = [
                    "status" => 100,
                    "message" => "No hay interlocutor asociado a la matricula ingresada"
                ];

                return response()->json($response);

            }

            $data = $this->obtenerDataRfc($interlocutor[0]->predio,2);

            if(!isset($data['T_ECUENTA'])){

                $response = [
                    "status" => 100,
                    "message" => "No hay datos vinculados a esta matricula."
                ];

                return response()->json($response);
            }

            $pdf = PDF::loadView('pdf',compact('data'))->setPaper('a4', 'landscape');
                return $pdf->stream();
        }

        public function obtenerDataRfc($interlocutor_predio,$tipo){
            $post = http_build_query([
                'interlocutor_predio' => str_pad($interlocutor_predio,10,'0',STR_PAD_LEFT),
                'tipo' => $tipo
            ]);

            $options = [
                'http' => [
                            'method' => 'POST',
                            'header' => 'Content-type: application/x-www-form-urlencoded',
                            'content' => $post
                        ]
            ];

            $context = stream_context_create($options);
            $result = file_get_contents('http://172.23.25.36/funciones-rfc/ZPSCD_ESTADO_CUENTAS.php', false, $context);

            return json_decode($result,true);
        }

    }

?>
