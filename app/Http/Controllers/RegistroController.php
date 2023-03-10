<?php 

    namespace App\Http\Controllers;
    
    use Illuminate\Http\Request;
    use App\TipoUsuario;
    use App\Usuario;
    use App\SolicitudUsuario;
    use App\MatriculaSolicitud;
    use App\HistorialSolicitud;
    use App\ArchivoSolicitud;

    require base_path() . '/vendor/PHPMailer_old/PHPMailerAutoload.php';

    use App\Jobs\MailJob;

    use Illuminate\Support\Facades\Mail;

    class RegistroController extends Controller
    {
        /**
         * Create a new controller instance.
         *
         * @return void
         */
        public function __construct(){
            
        }

        public function obtener_tipos_usuario(){

            $tipos_usuarios = TipoUsuario::all();

            foreach ($tipos_usuarios as &$tipo) {
                
                $tipo->select = false;

            }

            return response()->json($tipos_usuarios);

        }

        public function pasos_registro(Request $request){

            $result = app('db')->select("   SELECT T2.*
                                            FROM CATASTRO.SERV_TIPO_USER_PASO_REGISTRO T1
                                            INNER JOIN CATASTRO.SERV_PASO_REGISTRO T2
                                            ON T2.ID = T1.PASO_REGISTRO_ID
                                            WHERE T1.TIPO_USUARIO_ID = $request->tipo_usuario");

            return response()->json($result);

        }

        public function getCreatedUser(Request $request)
        {
            $result = Usuario::where('email',$request->email)->first();

            return response()->json($result);

        }

        public function obtener_campos_especiales(Request $request){

            $result = app('db')->select("   SELECT CAMPO, ETIQUETA
                                            FROM CATASTRO.SERV_TIPO_USER_CAMPO_ESP
                                            WHERE TIPO_USUARIO_ID = $request->tipo_usuario");

            return response()->json($result);

        }

        public function obtener_adjuntos(Request $request){

            $adjuntos = app('db')->select(" SELECT
                                                ARCHIVOS_ADJUNTOS,
                                                NOMBRES_ADJUNTOS
                                            FROM CATASTRO.SERV_TIPO_USUARIO
                                            WHERE ID = $request->tipo_usuario");

            if ($adjuntos) {
                
                $adjuntos = $adjuntos[0];

                $adjuntos->nombres_adjuntos = explode(",", $adjuntos->nombres_adjuntos);

            }

            return response()->json($adjuntos);

        }

        public function registrar_solicitud(Request $request){

            $tipo_usuario = json_decode($request->tipo_usuario);
            $datos = json_decode($request->datos_formulario);
            $matriculas = json_decode($request->matriculas);
            $number_files = json_decode($request->number_files);
            $number_attachments = json_decode($request->number_attachments);

             // Validar que no exista ya una solicitud de usuario o un usuario creado 

            $result = Usuario::where('email', $datos->email)->orWhere('dpi', $datos->dpi)->first();

            if ($result) {
                
                /* Validar la solicitud del usuario, si esta rechazada permitir generar otra */
                $solicitud = SolicitudUsuario::where('usuario_id', $result->id)->first();

                if ($solicitud->estatus != 'R') {
                    
                    $data = [
                        "status" => 100,
                        "message" => "Ya existe una solicitud de usuario en proceso o aprobada con el correo o dpi proporcionados.",
                        "title" => "Error...",
                        "icon" => "error"
                    ];

                    return response()->json($data);

                } else {

                    $data = [
                        "status" => 100,
                        "message" => "Hay una solicitud rechazada con el correo o dpi proporcionados.",
                        "title" => "Error...",
                        "icon" => "error"
                    ];

                    return response()->json($data);

                }

            }

            // Validar que el número de archivos adjuntos concuerde con la cantidad solicitada
            if ($number_files != $number_attachments) {
                
                $data = [
                    "status" => 100,
                    "message" => "Hacen falta archivos adjuntos.",
                    "title" => "Error...",
                    "icon" => "error"
                ];

            }


            // Creación del registro del usuario

            $usuario = new Usuario();
            $usuario->nombres = $datos->nombres;
            $usuario->apellidos = $datos->apellidos;
            $usuario->sexo = $datos->sexo;
            $usuario->email = $datos->email;
            $usuario->password = md5($datos->password);
            $usuario->direccion = $datos->direccion;
            $usuario->telefono = $datos->telefono;
            $usuario->dpi = $datos->dpi;
            $usuario->estatus = 'E';
            $usuario->representacion_legal = $datos->representacion_legal;
            $usuario->carne_abogado = $datos->carne_abogado;
            $usuario->carne_valuador = $datos->carne_valuador;

            $usuario->save();

            // Creación de la solicitud

            $solicitud = new SolicitudUsuario();
            $solicitud->usuario_id = $usuario->id;
            $solicitud->estatus = 'P';
            $solicitud->tipo_solicitud_id = 1;
            $solicitud->save();

            // Registrar los archivos adjuntos

            for ($i = 1; $i <= $number_files; $i++) { 
                
                $nombre = $request->file('file' . $i)->getClientOriginalName();
                $identificador = uniqid() . '.' . $request->file('file' . $i)->extension();

                if($request->file('file' . $i)->move('archivos', $identificador)){
                    
                    /* Registrar en la base de datos */
                    $archivo = new ArchivoSolicitud();
                    $archivo->solicitud_id = $solicitud->id;
                    $archivo->nombre = $nombre;
                    $archivo->path = $identificador;
                    $archivo->save();

                }

            }

            // Registro de las matriculas, si las hubiere

            foreach ($matriculas as $matricula) {
                
                $matricula_solicitud = new MatriculaSolicitud();
                $matricula_solicitud->matricula = $matricula;
                $matricula_solicitud->estado = 'P';
                $matricula_solicitud->solicitud_id = $solicitud->id;
                $matricula_solicitud->save();

            }

            // Registrar el rol del usuario
            $result = app('db')->table('SERV_USUARIO_TIPO')->insert([
                'usuario_id' => $usuario->id,
                'tipo_usuario_id' => $request->tipo_usuario,
                'estatus' => 'P',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'solicitud_id' => $solicitud->id
            ]);

            // Registrar en el historial de la solicitud
            $historial = new HistorialSolicitud();
            $historial->comentario = "Ingreso de solicitud de creación de usuario.";
            $historial->solicitud_id = $solicitud->id;
            $historial->save();

            $datos_correo = [
                // Correo para el solicitante
                [
                    "solicitud" => $solicitud->id,
                    "email" => $datos->email,
                    "body" => '<p>Se ha generado una solicitud de creación de usuario.  Se procederá a verificar la información proporcionada para luego continuar con la activación del usuario solicitado.</p>',
                    "body" =>   '<p>Estimado(a): ' . $usuario->nombres . ' ' . $usuario->apellidos . '</p>' .
                                '<p>Su gestión para la habilitación de usuario para acceder a los servicios catastrales en línea ha sido ingresada exitosamente.</p>' . 
                                '<p>Cuando su gestión haya sido aprobada se le estará notificando por esta vía</p>' .
                                '<p>Atentamente, </p>' .
                                '<p><strong>Dirección de Catastro y Administración del IUSI</strong></p>',
                                '<p><strong>Teléfono: 2285-8600 / 2285-8611</strong></p>',
                    "subject" => 'Solicitud de Creación de Usuario No. ' . $solicitud->id
                ],
                // Correo para el administrador
                [
                    "solicitud" => $solicitud->id,
                    "email" => 'caherrera@muniguate.com',
                    "body" => '<p>Se ha generado una nueva solicitud de habilitación de usuario No. ' . $solicitud->id . '</p><p>Por favor ingrese a la siguiente dirección para verificar la información</p><p> <a href="https://udicat.muniguate.com/apps/catastro-enlinea-app/#/admin">Ingresar</a> </p>',
                    "subject" => 'Solicitud de Creación de Usuario No. ' . $solicitud->id
                ]
            ];

            
            \Queue::push(new MailJob($datos_correo));

            // Enviar correo de notificación
            $result = $this->enviar_correo($datos_correo);

            // Enviar un mensaje de registro exitoso
            $data = [
                "status" => 200,
                "message" => "La solicitud de creación de usuario ha sido enviada exitosamente.  Se ha enviado un correo electrónico a la dirección <b>" . $datos->email . "</b> como confirmación del ingreso de la solicitud." ,
                "title" => "Excelente!",
                "icon" => "success",
                "data" => $solicitud
            ];

            return response()->json($data);

        }

        public function editar_solicitud(Request $request){

            $tipo_usuario = json_decode($request->tipo_usuario);
            $datos = json_decode($request->datos_formulario);
            $matriculas = json_decode($request->matriculas);
            $number_files = json_decode($request->number_files);
            $number_attachments = json_decode($request->number_attachments);
            $getemail = $request->getemail;

            // Validar que el número de archivos adjuntos concuerde con la cantidad solicitada
            if ($number_files != $number_attachments) {
                
                $data = [
                    "status" => 100,
                    "message" => "Hacen falta archivos adjuntos.",
                    "title" => "Error...",
                    "icon" => "error"
                ];

            }

            Usuario::where('email',$getemail)->update([
                'nombres' => $datos->nombres,
                'apellidos' => $datos->apellidos,
                'sexo' => $datos->sexo,
                'direccion' => $datos->direccion,
                'telefono' => $datos->telefono,
                'dpi' => $datos->dpi,
                'estatus' => 'E',
                'representacion_legal' => $datos->representacion_legal,
                'carne_abogado' => $datos->carne_abogado,
                'carne_valuador' => $datos->carne_valuador
            ]);

            $usuario = Usuario::where('email',$getemail)->first();

            // Creación de la solicitud

            $solicitud = new SolicitudUsuario();
            $solicitud->usuario_id = $usuario->id;
            $solicitud->estatus = 'P';
            $solicitud->tipo_solicitud_id = 1;
            $solicitud->save();

            // Registrar los archivos adjuntos

            for ($i = 1; $i <= $number_files; $i++) { 
                
                $nombre = $request->file('file' . $i)->getClientOriginalName();
                $identificador = uniqid() . '.' . $request->file('file' . $i)->extension();

                if($request->file('file' . $i)->move('archivos', $identificador)){
                    
                    /* Registrar en la base de datos */
                    $archivo = new ArchivoSolicitud();
                    $archivo->solicitud_id = $solicitud->id;
                    $archivo->nombre = $nombre;
                    $archivo->path = $identificador;
                    $archivo->save();

                }

            }

            // Registro de las matriculas, si las hubiere

            foreach ($matriculas as $matricula) {
                
                $matricula_solicitud = new MatriculaSolicitud();
                $matricula_solicitud->matricula = $matricula;
                $matricula_solicitud->estado = 'P';
                $matricula_solicitud->solicitud_id = $solicitud->id;
                $matricula_solicitud->save();

            }

            // Registrar el rol del usuario
            $result = app('db')->table('SERV_USUARIO_TIPO')->insert([
                'usuario_id' => $usuario->id,
                'tipo_usuario_id' => $request->tipo_usuario,
                'estatus' => 'P',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'solicitud_id' => $solicitud->id
            ]);

            // Registrar en el historial de la solicitud
            $historial = new HistorialSolicitud();
            $historial->comentario = "Ingreso de solicitud de creación de usuario.";
            $historial->solicitud_id = $solicitud->id;
            $historial->save();

            $datos_correo = [
                // Correo para el solicitante
                [
                    "solicitud" => $solicitud->id,
                    "email" => $datos->email,
                    "body" => '<p>Se ha generado una solicitud de creación de usuario.  Se procederá a verificar la información proporcionada para luego continuar con la activación del usuario solicitado.</p>',
                    "body" =>   '<p>Estimado(a): ' . $usuario->nombres . ' ' . $usuario->apellidos . '</p>' .
                                '<p>Su gestión para la habilitación de usuario para acceder a los servicios catastrales en línea ha sido ingresada exitosamente.</p>' . 
                                '<p>Cuando su gestión haya sido aprobada se le estará notificando por esta vía</p>' .
                                '<p>Atentamente, </p>' .
                                '<p><strong>Dirección de Catastro y Administración del IUSI</strong></p>',
                                '<p><strong>Teléfono: 2285-8600 / 2285-8611</strong></p>',
                    "subject" => 'Solicitud de Creación de Usuario No. ' . $solicitud->id
                ],
                // Correo para el administrador
                [
                    "solicitud" => $solicitud->id,
                    "email" => 'caherrera@muniguate.com',
                    "body" => '<p>Se ha generado una nueva solicitud de habilitación de usuario No. ' . $solicitud->id . '</p><p>Por favor ingrese a la siguiente dirección para verificar la información</p><p> <a href="https://udicat.muniguate.com/apps/catastro-enlinea-app/#/admin">Ingresar</a> </p>',
                    "subject" => 'Solicitud de Creación de Usuario No. ' . $solicitud->id
                ]
            ];

            
            \Queue::push(new MailJob($datos_correo));

            // Enviar correo de notificación
            $result = $this->enviar_correo($datos_correo);

            // Enviar un mensaje de registro exitoso
            $data = [
                "status" => 200,
                "message" => "La solicitud de creación de usuario ha sido enviada exitosamente.  Se ha enviado un correo electrónico a la dirección <b>" . $datos->email . "</b> como confirmación del ingreso de la solicitud." ,
                "title" => "Excelente!",
                "icon" => "success",
                "data" => $solicitud
            ];

            return response()->json($data);

        }

        public function enviar_correo($datos){

            foreach ($datos as $data) {

                $data = (object) $data;

                $mail = new \PHPMailer(true); 

                $mail->Host = 'mail2.muniguate.com';  
                $mail->isSMTP();  
                $mail->Username   = 'soportecatastro';                  
                $mail->Password   = 'catastro2015';
                $mail->CharSet = 'UTF-8';

                $mail->setFrom('no-reply@muniguate.com');
                $mail->isHTML(true);
                $mail->addAddress($data->email);
                $mail->Subject = $data->subject;
                $mail->Body = $data->body;

                try {
                    
                    $mail->send();

                } catch (\Throwable $th) {
                    
                }
                

            }

            return true;
                       
        }

        public function upload_file(Request $request){

            $nombre = $request->file('file')->getClientOriginalName();
            $identificador = uniqid() . '.' . $request->file('file')->extension();

            if($request->file('file')->move('archivos', $identificador)){
                
                /* Registrar en la base de datos */
                $archivo = new ArchivoSolicitud();
                $archivo->solicitud_id = $request->solicitud_id;
                $archivo->nombre = $nombre;
                $archivo->path = $identificador;
                $archivo->save();

            }

            $response = [
                "status" => 200
            ];

            return response()->json($response);

        }

        public function test_mail(){

            $data = [];

        }
        
    }


?>