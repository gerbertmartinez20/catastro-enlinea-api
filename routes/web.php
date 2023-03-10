<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {

    return $router->app->version();
    
});

// Se obtienen los tipos de usuario que se pueden registrar
$router->get('/obtener_tipos_usuario', 'RegistroController@obtener_tipos_usuario');

// Se obtienen los pasos necesarios para completar el registro
// Dependiendo del tipo de usuario
$router->post('/pasos_registro', 'RegistroController@pasos_registro');

// Obtener los campos especiales para el formulario de registro
// Estos dependen del tipo de usuario
$router->post('/obtener_campos_especiales', 'RegistroController@obtener_campos_especiales');

// Se registra la solicitud de usuario
$router->post('/registrar_solicitud', 'RegistroController@registrar_solicitud');

// Se modifica el registro del usuario
$router->post('/editar_solicitud', 'RegistroController@editar_solicitud');

// Obtener todas las solicitudes pendientes de atender 
$router->post('/obtener_solicitudes', 'SolicitudController@obtener_solicitudes');

// Obtener el detalle de la solicitud pendiente
$router->post('/detalle_solicitud', 'SolicitudController@detalle_solicitud');

// Cambiar el estado de una matricula, incluida en una solicitud de usuario
$router->post('/cambiar_estado_matricula', 'SolicitudController@cambiar_estado_matricula');

// Cambiar el estado de una solicitud de usuario
$router->post('/cambiar_estado_solicitud', 'SolicitudController@cambiar_estado_solicitud');

// Probar el envio de correo 
$router->get('/test_mail', 'MailController@test_mail');

// Actualizar el detalle del usuario
$router->post('/actualizar_detalle_usuario', 'SolicitudController@actualizar_detalle_usuario');

// Obtener la lista de documentos que se deberán de adjuntar
$router->post('/obtener_adjuntos_registro', 'RegistroController@obtener_adjuntos');

// Subir archivos en la solicitud de creación de usuario
$router->post('/upload_file_registro', 'RegistroController@upload_file');

// Obtener los documentos adjuntos de la solicitud
$router->post('/obtener_adjuntos', 'SolicitudController@obtener_adjuntos');

// Enviar email desde solicitud
$router->post('/enviar_email', 'SolicitudController@enviar_email');

// Iniciar sesión
$router->post('/login', 'LoginController@login');

// Obtener técnicos
$router->post('/obtener_tecnicos', 'SolicitudController@obtener_tecnicos');

// Asignar técnico
$router->post('/asignar_tecnico', 'SolicitudController@asignar_tecnico');

// Registrar comentario en la bitácora
$router->post('/registrar_comentario', 'SolicitudController@registrar_historial');

// Obtener los motivos de rechazo
$router->post('/motivos_rechazo', 'SolicitudController@motivos_rechazo');

// Login para página municipal
$router->post('/public_login', 'LoginController@public_login');

// Obtener las matrículas registradas de un contribuyente
$router->post('/matriculas_contribuyente', 'ContribuyenteController@obtener_matriculas');

// Adjuntar archivos a una solicitud
$router->post('/adjuntar_archivos_solicitud', 'SolicitudController@adjuntar_archivos');

// Obtener el interlocutor de una matricula
$router->post('/matricula_interlocutor', 'ContribuyenteController@matricula_interlocutor');

// Genera el estado de cuenta de interlocutor predio
$router->get('/estado_cuenta', 'ContribuyenteController@estado_cuenta');

// Obtener los servicios disponibles para validación
$router->post('/servicios_validacion', 'ServicioController@servicios_validacion');

// Validar documento
$router->post('/validar_documento', 'ServicioController@validar_documento');

// Registrar el documento emitido
$router->post('/registrar_documento', 'ServicioController@registrar_documento');

// Obtener los datos personales del contribuyente 
$router->post('/datos_personales', 'ContribuyenteController@datos_personales');

// Actualizar el perfil del contribuyente
$router->post('/actualizar_perfil', 'ContribuyenteController@actualizar_perfil');

// Obtener las matrículas asociadas a una cuenta
$router->post('/matriculas_registradas', 'ContribuyenteController@matriculas_registradas');

// Obtener los roles registrados a una cuenta
$router->post('/roles_registrados', 'ContribuyenteController@roles_registrados');

// Ingresar una solicitud para una nueva matrícula
$router->post('/ingresar_solicitud', 'ContribuyenteController@ingresar_solicitud');

// Ingresar solicitud para un nuevo rol 
$router->post('/ingresar_solicitud_rol', 'ContribuyenteController@ingresar_solicitud_rol');

// Solicitar roles faltantes de un contribuyente
$router->post('/roles_faltantes', 'ContribuyenteController@roles_faltantes');

// Cambiar el estado de la solicitud de asignación de nuevo rol
$router->post('/cambiar_estado_rol', 'SolicitudController@cambiar_estado_rol');


//RUTAS GMARTINEZ 

// Validar si existe el usuario en las tablas de catastro en linea para productos catastrales en linea
$router->post('/consultar_existencia', 'ProductosCatastrales\ProCasController@login');

// Obtener las matriculas que tiene relacionadas el usuario
$router->post('/consultar_matriculas', 'ProductosCatastrales\ProCasController@obtener_matriculas');

// Obtener las matriculas que tiene relacionadas el usuario
$router->post('/validar_requisitos', 'ProductosCatastrales\ProCasController@validar_requisitos');

// Grabar los datos cuando se finaliza el proceso
$router->post('/finalizar_proceso', 'ProductosCatastrales\ProCasController@notificar_finalizado');

// Obtener las opciones de los diferentes productos que se ofrecen
$router->get('/consultar_opciones', 'ProductosCatastrales\ProCasController@obtener_opciones');

// Grabar usuario nuevo que no exista en la base de datos del catastro en linea
$router->post('/usuario_nuevo', 'ProductosCatastrales\ProCasController@ingresar_usuario');

// Obtener los datos del usuario que ya fue registrado para mostrarlos y validarlos en el registro
$router->post('/obtener_usuario_creado','RegistroController@getCreatedUser');