<?php

namespace App\Http\Controllers;

use App\Fpdf\FpdfClass;
use App\Fpdf\Rotation;
use App\Fpdf\FpdfEstadoCuenta;
use App\Models\Abono;
use App\Models\Actividades;
use App\Models\Cliente;
use App\Models\Correlativo;
use App\Models\Departamentos;
use App\Models\Internet;
use App\Models\Municipios;
use App\Models\Ordenes;
use App\Models\Reconexion;
use App\Models\Suspensiones;
use App\Models\Tecnicos;
use App\Models\Traslados;
use App\Models\Tv;
use App\Models\Velocidades;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use DataTables;

class ClientesController extends Controller
{
    public function __construct(){
        // verifica si la session esta activa
        $this->middleware('auth');
    }
    public function index(){

        $obj = Cliente::where('activo',1)->where('id_sucursal',Auth::user()->id_sucursal)->get();

        return view('clientes.index',compact('obj'));
    }

    public function cliente_genCargo($id){
        $inter = Internet::where('id_cliente',$id)->get();
        
        $fecha_actual = date('Y-m-d');
        $fecha_vence = strtotime ( '+10 day' , strtotime ( $fecha_actual ) ) ;
        $fecha_vence = date ( 'Y-m-d' , $fecha_vence );
        $mes_servicio = strtotime ( '-1 month' , strtotime ( $fecha_actual ) ) ;
        $mes_servicio = date ( 'Y-m-d' , $mes_servicio );

        $abono = new Abono();
        $abono->id_cliente = $id;
        $abono->tipo_servicio = 1;
        $abono->mes_servicio = $mes_servicio;
        $abono->fecha_aplicado = date('Y-m-d');
        $abono->cargo = $inter[0]->cuota_mensual;
        $abono->abono = 0.00;
        $abono->fecha_vence = $fecha_vence;
        $abono->anulado = 0;
        $abono->pagado = 0;
        $abono->save();
        $obj_controller_bitacora=new BitacoraController();	
        $obj_controller_bitacora->create_mensaje('Se creo un cargo manual para el cliente id: '.$id);
            
        flash()->success("Cargo manual generado  exitosamente!")->important();
        return back();


    }
    /*
    public function getClientes(Request $request){
        if ($request->ajax()) {
            $data = Cliente::where('activo',1)->where('id_sucursal',Auth::user()->id_sucursal)->get();
            return Datatables()->of($data)
                ->addIndexColumn()
                ->addColumn('action', function($row){
                    $actionBtn = '<div class="btn-group dropup mr-1 mt-2"><button type="button" class="btn btn-primary">Acciones</button><button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="mdi mdi-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="'.route("clientes.gen_cargo",$row->id).'">Generar cargo</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="#" onclick="detallesCliente('.$row->id.')">Detalles</a>
                        <a class="dropdown-item" href="'.route("clientes.edit",$row->id).'">Editar</a>
                        <a class="dropdown-item" href="#" onclick="eliminar('.$row->id.')">Eliminar</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="'.route("clientes.contrato",$row->id).'">Contrato</a>
                        <a class="dropdown-item" href="'.route("cliente.estado_cuenta.index",$row->id).'">Estado de cuenta</a>
                        <a class="dropdown-item" href="'.route("cliente.ordenes.index",$row->id).'">Ordenes</a>
                        <a class="dropdown-item" href="'.route("cliente.suspensiones.index",$row->id).'">Suspenciones</a>
                        <a class="dropdown-item" href="'.route("cliente.reconexiones.index",$row->id).'">Reconexiones</a>
                        <a class="dropdown-item" href="'.route("cliente.traslados.index",$row->id).'">Traslados</a>
                        
                    </div>
                </div>';
                    return $actionBtn;
                })
                ->addColumn('internet', function($row){
                    $internet='';
                    if($row->internet==1){ $internet = '<div class="col-md-9 badge badge-pill badge-success">Activo</div>';}
                    if($row->internet==0){ $internet = '<div class="col-md-9 badge badge-pill badge-secondary">Inactivo</div>'; }
                    if($row->internet==2){ $internet = '<div class="col-md-9 badge badge-pill badge-danger">Suspendido</div>'; }
                    if($row->internet==3){ $internet = '<div class="col-md-9 badge badge-pill badge-warning">Vencido</div>'; }
                    return $internet;

                })
                ->addColumn('television', function($row){
                    $tv='';
                    if($row->tv==1){ $tv = '<div class="col-md-9 badge badge-pill badge-success">Activo</div>';}
                    if($row->tv==0){ $tv = '<div class="col-md-9 badge badge-pill badge-secondary">Inactivo</div>'; }
                    if($row->tv==2){ $tv = '<div class="col-md-9 badge badge-pill badge-danger">Suspendido</div>'; }
                    if($row->tv==3){ $tv = '<div class="col-md-9 badge badge-pill badge-warning">Vencido</div>'; }
                    return $tv;

                })
                ->rawColumns(['action','internet','television'])
                ->make(true);
                
        }
        
    }*/
    public function getClientes(Request $request){
        $columns = array( 
            0 =>'codigo',
            1 =>'nombre',
            2 =>'telefono1',
            3 => 'dui',
            4=> 'internet',
            5=> 'tv',
            6=> 'id'
        );

        $totalData = Cliente::where('activo',1)->count();

        $totalFiltered = $totalData; 
        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        if(empty($request->input('search.value')))
        {   
            $posts = Cliente::
            offset($start)
            ->limit($limit)
            ->orderBy($order,$dir)
            ->where('activo','1')
            ->get();
        }else {
            $search = $request->input('search.value');
            $posts =  Cliente::orWhere('nombre', 'LIKE',"%{$search}%")
            ->where('activo','1')
            ->orwhere('codigo','LIKE',"%{$search}%")
            ->where('activo','1')
            ->offset($start)
            ->limit($limit)
            ->orderBy($order,$dir)
            ->get();

            $totalFiltered = Cliente::orwhere('codigo','LIKE',"%{$search}%")
            ->where('activo','1')
            ->orWhere('nombre', 'LIKE',"%{$search}%")
            ->where('activo','1')
            ->count();
        }

        $data = array();
        if(!empty($posts))
        {
            foreach ($posts as $post)
            {
                //$show =  route('posts.show',$post->id);
                //$edit =  route('posts.edit',$post->id);

                $nestedData['codigo'] = $post->codigo;
                $nestedData['nombre'] = $post->nombre;
                $nestedData['telefono1'] = $post->telefono1;
                $nestedData['dui'] = $post->dui;
                $internet='';
                if($post->internet==1){ $internet = "<div class='col-md-9 badge badge-pill badge-success'>Activo</div>";}
                if($post->internet==0){ $internet = "<div class='col-md-9 badge badge-pill badge-secondary'>Inactivo</div>"; }
                if($post->internet==2){ $internet = "<div class='col-md-9 badge badge-pill badge-danger'>Suspendido</div>"; }
                if($post->internet==3){ $internet = "<div class='col-md-9 badge badge-pill badge-warning'>Vencido</div>"; }
                //return $internet;
                $nestedData['internet']=$internet;
                $tv='';
                if($post->tv==1){ $tv = '<div class="col-md-9 badge badge-pill badge-success">Activo</div>';}
                if($post->tv==0){ $tv = '<div class="col-md-9 badge badge-pill badge-secondary">Inactivo</div>'; }
                if($post->tv==2){ $tv = '<div class="col-md-9 badge badge-pill badge-danger">Suspendido</div>'; }
                if($post->tv==3){ $tv = '<div class="col-md-9 badge badge-pill badge-warning">Vencido</div>'; }
                $nestedData['television']=$tv;
                $actionBtn = '
                <div class="btn-group dropup mr-1 mt-2">
                    <button type="button" class="btn btn-primary">Acciones</button>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="mdi mdi-chevron-down"></i>
                    </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="'.route("clientes.gen_cargo",$post->id).'">Generar cargo</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="#" onclick="detallesCliente('.$post->id.')">Detalles</a>
                            <a class="dropdown-item" href="'.route("clientes.edit",$post->id).'">Editar</a>
                            <a class="dropdown-item" href="#" onclick="eliminar('.$post->id.')">Eliminar</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="'.route("clientes.contrato",$post->id).'">Contrato</a>
                            <a class="dropdown-item" href="'.route("cliente.estado_cuenta.index",$post->id).'">Estado de cuenta</a>
                            <a class="dropdown-item" href="'.route("cliente.ordenes.index",$post->id).'">Ordenes</a>
                            <a class="dropdown-item" href="'.route("cliente.suspensiones.index",$post->id).'">Suspenciones</a>
                            <a class="dropdown-item" href="'.route("cliente.reconexiones.index",$post->id).'">Reconexiones</a>
                            <a class="dropdown-item" href="'.route("cliente.traslados.index",$post->id).'">Traslados</a>
                        </div>
                </div>';
                $nestedData['action']=$actionBtn;

                //$nestedData['created_at'] = date('j M Y h:i a',strtotime($post->created_at));
                //$nestedData['options'] = "&emsp;<a href='{$show}' title='SHOW' ><span class='glyphicon glyphicon-list'></span></a>
                //                    &emsp;<a href='{$edit}' title='EDIT' ><span class='glyphicon glyphicon-edit'></span></a>";
                $data[] = $nestedData;

            }
        }

        $json_data = array(
            "draw"            => intval($request->input('draw')),  
            "recordsTotal"    => intval($totalData),  
            "recordsFiltered" => intval($totalFiltered), 
            "data"            => $data   
        );

        echo json_encode($json_data); 
    }

    public function create(){
        $obj_departamento = Departamentos::all();
        $correlativo_cod_cliente = $this->correlativo(3,6);
        $correlativo_contra_tv = $this->correlativo(4,6);
        $correlativo_contra_inter = $this->correlativo(5,6);
        $velocidades = Velocidades::all();
        return view('clientes.create',compact('obj_departamento','correlativo_cod_cliente','correlativo_contra_tv','correlativo_contra_inter','velocidades'));
    }

    public function municipios($id){

        $municipios = Municipios::where('id_departamento',$id)->get();
       return response()->json(
            $municipios-> toArray()  
        );

    }

    public function store(Request $request){

        //dd($request->all());
       //Guarando el registro de cliente

        $cliente = new Cliente();
        $cliente->codigo = $this->correlativo(3,6);
        $cliente->nombre = $request->nombre;
        $cliente->email = $request->email;
        $cliente->dui = $request->dui;
        $cliente->nit = $request->nit;
        if($request->fecha_nacimiento!=""){ 
            
            $cliente->fecha_nacimiento = Carbon::createFromFormat('d/m/Y', $request->fecha_nacimiento);
        }
        $cliente->telefono1 = $request->telefono1;
        $cliente->telefono2 = $request->telefono2;
        $cliente->id_municipio = $request->id_municipio;
        $cliente->dirreccion = $request->dirreccion;
        $cliente->dirreccion_cobro = $request->dirreccion_cobro;
        $cliente->ocupacion = $request->ocupacion;
        $cliente->condicion_lugar = $request->condicion_lugar;
        $cliente->nombre_dueno = $request->nombre_dueno;
        $cliente->numero_registro = $request->numero_registro;
        $cliente->giro = $request->giro;
        $cliente->colilla = $request->colilla;
        $cliente->tipo_documento = $request->tipo_documento;
        $cliente->referencia1 = $request->referencia1;
        $cliente->telefo1 = $request->telefo1;
        $cliente->referencia2 = $request->referencia2;
        $cliente->telefo2 = $request->telefo2;
        $cliente->referencia3 = $request->referencia3;
        $cliente->telefo3 = $request->telefo3;
        if($request->colilla==1){
            $cliente->internet = 1;
            $cliente->tv = 0;
        }
        if($request->colilla==2){
            $cliente->tv = 1;
            $cliente->internet = 0;
        }
        if($request->colilla==3){
            $cliente->tv = 1;
            $cliente->internet = 1;
        }
        $cliente->cordenada = $request->cordenada;
        $cliente->nodo = $request->nodo;
        $cliente->activo = 1;
        $cliente->id_sucursal = Auth::user()->id_sucursal;
        $cliente->save();
        $this->setCorrelativo(3);


        //obteniendo el ultimo cliente
        $ultimo_cliente = Cliente::all()->last();
        $id_cliente = $ultimo_cliente->id;
        $codigo_cliente=$ultimo_cliente->codigo;

        //guardaondo mensajes en bitacora
        $obj_controller_bitacora=new BitacoraController();	
        $obj_controller_bitacora->create_mensaje('Cliente creado: '.$codigo_cliente);


        //Si colill es igual a 1 se guarda en tabla internets
        if($request->colilla==1){


            $internet = new Internet();
            $internet->id_cliente = $id_cliente;
            $internet->numero_contrato = $this->correlativo(5,6);
            if($request->fecha_instalacion!=""){
                $internet->fecha_instalacion = Carbon::createFromFormat('d/m/Y', $request->fecha_instalacion);

            }
            if($request->fecha_primer_fact!=""){
                $internet->fecha_primer_fact = Carbon::createFromFormat('d/m/Y', $request->fecha_primer_fact);

            }
            $cuota_mensual = explode(" ", $request->cuota_mensual);
            $internet->cuota_mensual = $cuota_mensual[1];

            if($request->costo_instalacion!=""){

                $costo_instalacion = explode(" ",$request->costo_instalacion);
                $internet->costo_instalacion = $costo_instalacion[1];
            }

            $internet->prepago = $request->prepago;
            $internet->dia_gene_fact = $request->dia_gene_fact;
            if($request->contrato_vence!=""){
                $internet->contrato_vence = Carbon::createFromFormat('d/m/Y', $request->contrato_vence);

            }
            $internet->periodo = $request->periodo;
            $internet->cortesia = $request->cortesia;
            $internet->velocidad = $request->velocidad;
            $internet->onu = $request->onu;
            $internet->onu_wifi = $request->onu_wifi;
            $internet->cable_red = $request->cable_red;
            $internet->router = $request->router;
            $internet->marca = $request->marca;
            $internet->modelo = $request->modelo;
            $internet->mac = $request->mac;
            $internet->serie = $request->serie;
            $internet->recepcion = $request->recepcion;
            $internet->trasmision = $request->trasmision;
            $internet->ip = $request->ip;
            $internet->tecnologia = $request->tecnologia;
            $internet->identificador = 1;
            $internet->activo = 1;
            $internet->save();

            $obj_controller_bitacora=new BitacoraController();	
            $obj_controller_bitacora->create_mensaje('Se creo servicio de internet para el cliente: '.$codigo_cliente.' con numero de contrato: '.$this->correlativo(5,6));
            
            $this->setCorrelativo(5);




        }

        //si collilla es igual 2 se guarda en tabla tvs
        if($request->colilla==2){

            $tv = new Tv();
            $tv->id_cliente = $id_cliente;
            $tv->numero_contrato = $this->correlativo(4,6);
            if($request->fecha_instalacion_tv!=""){
                $tv->fecha_instalacion = Carbon::createFromFormat('d/m/Y', $request->fecha_instalacion_tv);

            }
            if($request->fecha_primer_fact_tv!=""){
                $tv->fecha_primer_fact = Carbon::createFromFormat('d/m/Y', $request->fecha_primer_fact_tv);

            }
            $cuota_mensual = explode(" ", $request->cuota_mensual_tv);
            $tv->cuota_mensual = $cuota_mensual[1];
            if($request->costo_instalacion_tv!=""){

                $costo_instalacion = explode(" ",$request->costo_instalacion_tv);
                $tv->costo_instalacion = $costo_instalacion[1];
            }
            $tv->prepago = $request->prepago_tv;
            $tv->dia_gene_fact = $request->dia_gene_fact_tv;
            if($request->contrato_vence_tv!=""){
                $tv->contrato_vence = Carbon::createFromFormat('d/m/Y', $request->contrato_vence_tv);

            }
            $tv->periodo = $request->periodo_tv;
            $tv->cortesia = $request->cortesia_tv;
            $tv->digital = $request->digital_tv;
            $tv->marca = $request->marca_tv;
            $tv->serie = $request->serie_tv;
            $tv->modelo = $request->modelo_tv;
            $tv->identificador = 2;
            $tv->activo = 1;
            $tv->save();

            $obj_controller_bitacora=new BitacoraController();	
            $obj_controller_bitacora->create_mensaje('Se creo servicio de tv para el cliente: '.$codigo_cliente.' con numero de contrato: '.$this->correlativo(4,6));
            
            $this->setCorrelativo(4);



        }

        //si collilla es igual a 3 va guarda en tabla tvs y internets
        if($request->colilla==3){

            $internet = new Internet();
            $internet->id_cliente = $id_cliente;
            $internet->numero_contrato = $this->correlativo(5,6);
            if($request->fecha_instalacion!=""){
                $internet->fecha_instalacion = Carbon::createFromFormat('d/m/Y', $request->fecha_instalacion);

            }
            if($request->fecha_primer_fact!=""){
                $internet->fecha_primer_fact = Carbon::createFromFormat('d/m/Y', $request->fecha_primer_fact);

            }
            $cuota_mensual = explode(" ", $request->cuota_mensual);
            $internet->cuota_mensual = $cuota_mensual[1];
            if($request->costo_instalacion!=""){

                $costo_instalacion = explode(" ",$request->costo_instalacion);
                $internet->costo_instalacion = $costo_instalacion[1];
            }
            $internet->prepago = $request->prepago;
            $internet->dia_gene_fact = $request->dia_gene_fact;
            if($request->contrato_vence!=""){
                $internet->contrato_vence = Carbon::createFromFormat('d/m/Y', $request->contrato_vence);

            }
            $internet->periodo = $request->periodo;
            $internet->cortesia = $request->cortesia_tv;
            $internet->velocidad = $request->velocidad;
            $internet->onu = $request->onu;
            $internet->onu_wifi = $request->onu_wifi;
            $internet->cable_red = $request->cable_red;
            $internet->router = $request->router;
            $internet->marca = $request->marca;
            $internet->modelo = $request->modelo;
            $internet->mac = $request->mac;
            $internet->serie = $request->serie;
            $internet->recepcion = $request->recepcion;
            $internet->trasmision = $request->trasmision;
            $internet->ip = $request->ip;
            $internet->tecnologia = $request->tecnologia;
            $internet->identificador = 1;
            $internet->activo = 1;
            $internet->save();

            $obj_controller_bitacora=new BitacoraController();	
            $obj_controller_bitacora->create_mensaje('Se creo servicio de internet para el cliente: '.$codigo_cliente.' con numero de contrato: '.$this->correlativo(5,6));
           
            $this->setCorrelativo(5);


            $tv = new Tv();
            $tv->id_cliente = $id_cliente;
            $tv->numero_contrato = $this->correlativo(4,6);
            if($request->fecha_instalacion_tv!=""){
                $tv->fecha_instalacion = Carbon::createFromFormat('d/m/Y', $request->fecha_instalacion_tv);

            }
            if($request->fecha_primer_fact_tv!=""){
                $tv->fecha_primer_fact = Carbon::createFromFormat('d/m/Y', $request->fecha_primer_fact_tv);

            }
            $cuota_mensual = explode(" ", $request->cuota_mensual_tv);
            $tv->cuota_mensual = $cuota_mensual[1];
            if($request->costo_instalacion_tv!=""){

                $costo_instalacion = explode(" ",$request->costo_instalacion_tv);
                $tv->costo_instalacion = $costo_instalacion[1];
            }
            $tv->prepago = $request->prepago_tv;
            $tv->dia_gene_fact = $request->dia_gene_fact_tv;
            if($request->contrato_vence_tv!=""){
                $tv->contrato_vence = Carbon::createFromFormat('d/m/Y', $request->contrato_vence_tv);

            }
            $tv->periodo = $request->periodo_tv;
            $tv->cortesia = $request->cortesia_tv;
            $tv->digital = $request->digital_tv;
            $tv->marca = $request->marca_tv;
            $tv->serie = $request->serie_tv;
            $tv->modelo = $request->modelo_tv;
            $tv->identificador = 2;
            $tv->activo = 1;
            $tv->save();

            $obj_controller_bitacora=new BitacoraController();	
            $obj_controller_bitacora->create_mensaje('Se creo servicio de tv para el cliente: '.$codigo_cliente.' con numero de contrato: '.$this->correlativo(4,6));
            
            $this->setCorrelativo(4);


        }
        flash()->success("Cliente y servicios creados exitosamente!")->important();
        return redirect()->route('clientes.index');

    }

    public function edit($id){
        $cliente = Cliente::find($id);
        $tv = Tv::where('id_cliente',$id)->where('activo',1)->get();
        $internet = Internet::where('id_cliente',$id)->where('activo',1)->get();
        $obj_departamento = Departamentos::all();
        $velocidades = Velocidades::all();

        return view('clientes.edit', compact('cliente','tv','internet','obj_departamento','velocidades'));

    }

    public function update(Request $request){
        //dd($request->all());

        $id_cliente = $request->id_cliente;
        $codigo_cliente = $request->codigo;
        if($request->colilla==1){
            $internet = 1;
            $tv = 0;
        }
        if($request->colilla==2){
            $tv = 1;
            $internet = 0;
        }
        if($request->colilla==3){
            $tv = 1;
            $internet = 1;
        }
        $fecha_nacimiento=null;
        if($request->fecha_nacimiento!=""){
            $fecha_nacimiento = Carbon::createFromFormat('d/m/Y', $request->fecha_nacimiento);

        }


        Cliente::where('id',$id_cliente)->update([
            'nombre' =>$request->nombre,
            'email' =>$request->email,
            'dui' =>$request->dui,
            'nit' =>$request->nit,
            'fecha_nacimiento' =>$fecha_nacimiento,
            'telefono1' =>$request->telefono1,
            'telefono2' =>$request->telefono2,
            'id_municipio' =>$request->id_municipio,
            'dirreccion' =>$request->dirreccion,
            'dirreccion_cobro' =>$request->dirreccion_cobro,
            'ocupacion' =>$request->ocupacion,
            'condicion_lugar' =>$request->condicion_lugar,
            'nombre_dueno' =>$request->nombre_dueno,
            'numero_registro' =>$request->numero_registro,
            'giro' =>$request->giro,
            'colilla' =>$request->colilla,
            'tipo_documento' =>$request->tipo_documento,
            'referencia1' =>$request->referencia1,
            'telefo1' =>$request->telefo1,
            'referencia2' =>$request->referencia2,
            'telefo2' =>$request->telefo2,
            'referencia3' =>$request->referencia3,
            'telefo3' =>$request->telefo3,
            'tv' =>$tv,
            'internet' =>$internet,
            'cordenada' =>$request->cordenada,
            'nodo' =>$request->nodo

        ]);
        

        //para internet
        $cliente = Cliente::find($id_cliente);
        $internet = $cliente->internet;
        $tv = $cliente->tv;

        $fecha_instalacion="";
        $fecha_primer_fact="";
        $contrato_vence="";

        if($request->fecha_instalacion!=""){
            $fecha_instalacion = Carbon::createFromFormat('d/m/Y', $request->fecha_instalacion);

        }
        if($request->fecha_primer_fact!=""){
            $fecha_primer_fact = Carbon::createFromFormat('d/m/Y', $request->fecha_primer_fact);

        }

        if($request->contrato_vence!=""){
            $contrato_vence = Carbon::createFromFormat('d/m/Y', $request->contrato_vence);

        }

        $cuota_mensual = explode(" ", $request->cuota_mensual);
        if($request->costo_instalacion!=""){

            $costo_instalacion = explode(" ",$request->costo_instalacion);
        }else{
            $costo_instalacion = array(0.00,0.00);
        }

        if($internet==1){
            $isset_internet = Internet::where('id_cliente',$id_cliente)->get();
            if(count($isset_internet)!=0){
                Internet::where('id_cliente',$id_cliente)->update([
                    'fecha_instalacion' => $fecha_instalacion,
                    'fecha_primer_fact' => $fecha_primer_fact,
                    'cuota_mensual' => $cuota_mensual[1],
                    'costo_instalacion' => $costo_instalacion[1],
                    'prepago' => $request->prepago,
                    'dia_gene_fact' => $request->dia_gene_fact,
                    'contrato_vence' => $contrato_vence,
                    'periodo' => $request->periodo,
                    'cortesia' => $request->cortesia,
                    'velocidad' => $request->velocidad,
                    'onu' => $request->onu,
                    'onu_wifi' => $request->onu_wifi,
                    'cable_red' => $request->cable_red,
                    'router' => $request->router,
                    'marca' => $request->marca,
                    'modelo' => $request->modelo,
                    'mac' => $request->mac,
                    'serie' => $request->serie,
                    'recepcion' => $request->recepcion,
                    'trasmision' => $request->trasmision,
                    'ip' => $request->ip,
                    'tecnologia' => $request->tecnologia,

                ]);

                $obj_controller_bitacora=new BitacoraController();	
                $obj_controller_bitacora->create_mensaje('Se edito servicio de internet para el cliente: '.$codigo_cliente);

            }else{

                $internet = new Internet();
                $internet->id_cliente = $id_cliente;
                $internet->numero_contrato = $this->correlativo(5,6);
                if($request->fecha_instalacion!=""){
                    $internet->fecha_instalacion = Carbon::createFromFormat('d/m/Y', $request->fecha_instalacion);

                }
                if($request->fecha_primer_fact!=""){
                    $internet->fecha_primer_fact = Carbon::createFromFormat('d/m/Y', $request->fecha_primer_fact);

                }
                $cuota_mensual = explode(" ", $request->cuota_mensual);
                $internet->cuota_mensual = $cuota_mensual[1];
                if($request->costo_instalacion){

                    $costo_instalacion = explode(" ",$request->costo_instalacion);
                    $internet->costo_instalacion = $costo_instalacion[1];
                }
                $internet->prepago = $request->prepago;
                $internet->dia_gene_fact = $request->dia_gene_fact;
                if($request->contrato_vence!=""){
                    $internet->contrato_vence = Carbon::createFromFormat('d/m/Y', $request->contrato_vence);

                }
                $internet->periodo = $request->periodo;
                $internet->cortesia = $request->cortesia;
                $internet->velocidad = $request->velocidad;
                $internet->onu = $request->onu;
                $internet->onu_wifi = $request->onu_wifi;
                $internet->cable_red = $request->cable_red;
                $internet->router = $request->router;
                $internet->marca = $request->marca;
                $internet->modelo = $request->modelo;
                $internet->mac = $request->mac;
                $internet->serie = $request->serie;
                $internet->recepcion = $request->recepcion;
                $internet->trasmision = $request->trasmision;
                $internet->ip = $request->ip;
                $internet->tecnologia = $request->tecnologia;
                $internet->identificador = 1;
                $internet->save();


                
                // En editar entonces cuando guarda numero de contrato en bitacora esta sumando un correlativo mas
                $obj_controller_bitacora=new BitacoraController();	
                $obj_controller_bitacora->create_mensaje('Se creo servicio de internet para el cliente: '.$codigo_cliente.' con numero de contrato: '.$this->correlativo(5,6));
                
                $this->setCorrelativo(5);

            }
        }

        $fecha_instalacion_tv="";
        $fecha_primer_fact_tv="";
        $contrato_vence_tv="";

        if($request->fecha_instalacion_tv!=""){
            $fecha_instalacion_tv = Carbon::createFromFormat('d/m/Y', $request->fecha_instalacion_tv);

        }
        if($request->fecha_primer_fact_tv!=""){
            $fecha_primer_fact_tv = Carbon::createFromFormat('d/m/Y', $request->fecha_primer_fact_tv);

        }

        if($request->contrato_vence_tv!=""){
            $contrato_vence_tv = Carbon::createFromFormat('d/m/Y', $request->contrato_vence_tv);

        }

        $cuota_mensual_tv = explode(" ", $request->cuota_mensual_tv);
        //$costo_instalacion = explode(" ",$request->costo_instalacion_tv);

        if($request->costo_instalacion!=""){

            $costo_instalacion = explode(" ",$request->costo_instalacion_tv);
        }else{
            $costo_instalacion = array(0.00,0.00);
        }

        if($tv==1){
            $isset_tv = Tv::where('id_cliente',$id_cliente)->get();

            if(count($isset_tv)!=0){
                Tv::where('id_cliente',$id_cliente)->update([
                    'fecha_instalacion' => $fecha_instalacion_tv,
                    'fecha_primer_fact' => $fecha_primer_fact_tv,
                    'cuota_mensual' => $cuota_mensual_tv[1],
                    'costo_instalacion' => $costo_instalacion[1],
                    'prepago' => $request->prepago_tv,
                    'dia_gene_fact' => $request->dia_gene_fact_tv,
                    'contrato_vence' => $contrato_vence_tv,
                    'periodo' => $request->periodo_tv,
                    'cortesia' => $request->cortesia_tv,
                    'digital' => $request->digital_tv,
                    'marca' => $request->marca_tv,
                    'modelo' => $request->modelo_tv,
                    'serie' => $request->serie_tv,

                ]);

                $obj_controller_bitacora=new BitacoraController();	
                $obj_controller_bitacora->create_mensaje('Se edito servicio de Televisi??n para el cliente: '.$codigo_cliente);

            }else{

                $tv = new Tv();
                $tv->id_cliente = $id_cliente;
                $tv->numero_contrato = $this->correlativo(4,6);
                if($request->fecha_instalacion_tv!=""){
                    $tv->fecha_instalacion = Carbon::createFromFormat('d/m/Y', $request->fecha_instalacion_tv);

                }
                if($request->fecha_primer_fact_tv!=""){
                    $tv->fecha_primer_fact = Carbon::createFromFormat('d/m/Y', $request->fecha_primer_fact_tv);

                }
                $cuota_mensual = explode(" ", $request->cuota_mensual_tv);
                $tv->cuota_mensual = $cuota_mensual[1];
                if($request->costo_instalacion_tv){

                    $costo_instalacion = explode(" ",$request->costo_instalacion_tv);
                    $tv->costo_instalacion = $costo_instalacion[1];
                }
                $tv->prepago = $request->prepago_tv;
                $tv->dia_gene_fact = $request->dia_gene_fact_tv;
                if($request->contrato_vence_tv!=""){
                    $tv->contrato_vence = Carbon::createFromFormat('d/m/Y', $request->contrato_vence_tv);

                }
                $tv->periodo = $request->periodo_tv;
                $tv->cortesia = $request->cortesia_tv;
                $tv->digital = $request->digital_tv;
                $tv->marca = $request->marca_tv;
                $tv->serie = $request->serie_tv;
                $tv->modelo = $request->modelo_tv;
                $internet->identificador = 2;
                $tv->save();
                
                $obj_controller_bitacora=new BitacoraController();	
                $obj_controller_bitacora->create_mensaje('Se creo servicio de tv para el cliente: '.$codigo_cliente.' con numero de contrato: '.$this->correlativo(4,6));
                
                $this->setCorrelativo(4);


            }
        }



        flash()->success("Cliente y servicios editados exitosamente!")->important();
        return redirect()->route('clientes.index');
    }

    public function details($id){
        $cliente = Cliente::where('id',$id)->get();
        if($cliente[0]->id_municipio!=0){

            $cliente = Cliente::select(
                                'clientes.id_municipio',
                                'clientes.codigo',
                                'clientes.nombre',
                                'clientes.email',
                                'clientes.dui',
                                'clientes.nit',
                                'clientes.fecha_nacimiento',
                                'clientes.telefono1',
                                'clientes.telefono2',
                                'clientes.dirreccion',
                                'clientes.dirreccion_cobro',
                                'clientes.ocupacion',
                                'clientes.condicion_lugar',
                                'clientes.nombre_dueno',
                                'clientes.numero_registro',
                                'clientes.giro',
                                'clientes.internet',
                                'clientes.tv',
                                'clientes.colilla',
                                'clientes.tipo_documento',
                                'clientes.referencia1',
                                'clientes.telefo1',
                                'clientes.referencia2',
                                'clientes.telefo2',
                                'clientes.referencia3',
                                'clientes.telefo3',
                                'clientes.cordenada',
                                'clientes.nodo',
                                'municipios.nombre as nombre_municipio',
                                'departamentos.nombre as nombre_departamento',
    
                                    )
                                ->join('municipios','clientes.id_municipio','=','municipios.id')
                                ->join('departamentos','municipios.id_departamento','=','departamentos.id')
                                ->where('clientes.id',$id)->get();
        }

        return response()->json(
            $cliente-> toArray()  
        );

    }

    public function internet_details($id){

        $internet_1 = Internet::where('id_cliente',$id)->where('activo',1)->get();
        $internet_2 = Internet::where('id_cliente',$id)->where('activo',2)->get();

        if(count($internet_1)!=0){
            $internet = $internet_1;
        }else{
            $internet = $internet_2;
        }

        return response()->json(
            $internet-> toArray()  
        );


    }

    public function tv_details($id){

        $tv_1 = Tv::where('id_cliente',$id)->where('activo',1)->get();
        $tv_2 = Tv::where('id_cliente',$id)->where('activo',2)->get();

        if(count($tv_1)!=0){
            $tv = $tv_1;
        }else{
            $tv = $tv_2;
        }

        return response()->json(
            $tv-> toArray()  
        );

    }

    public function destroy($id){
        Cliente::where('id',$id)->update(['activo' =>0]);

        $obj_controller_bitacora=new BitacoraController();	
        $obj_controller_bitacora->create_mensaje('Cliente eliminado con id: '.$id);
        flash()->success("Cliente eliminado exitosamente!")->important();
        return redirect()->route('clientes.index');
    }

    public function contrato($id){
        $cliente = Cliente::find($id);
        $contrato_tv= Tv::select('id','id_cliente','numero_contrato','fecha_instalacion','contrato_vence','identificador','activo')->where('id_cliente',$id);

        $contratos= Internet::select('id','id_cliente','numero_contrato','fecha_instalacion','contrato_vence','identificador','activo')
                            ->where('id_cliente',$id)
                            ->unionAll($contrato_tv)
                            ->get();

        $inter_activos = Internet::where('id_cliente',$id)->where('activo',1)->get();
        $tv_activos = Tv::where('id_cliente',$id)->where('activo',1)->get();
       

        return view('contratos.index',compact('contratos','cliente','id','tv_activos','inter_activos'));
        
        
    }



    public function contrato_activo($id,$identificador){    
        
        if($identificador==1){
            $internet = Internet::find($id);
            $id_cliente = $internet->id_cliente;
            $num_contrato_inter = $internet->numero_contrato;
            if($internet->activo==0 || $internet->activo==2){
                $internet_con = Internet::where('id_cliente',$id_cliente)->where('activo',1)->get()->count();
                //return $internet_con;
                if($internet_con==0){
                    
                    Internet::where('id',$id)->update(['activo'=>1]);
                    Cliente::where('id',$id_cliente)->update(['internet'=>1]);
                    $obj_controller_bitacora=new BitacoraController();	
                    $obj_controller_bitacora->create_mensaje('Contrato: '.$num_contrato_inter.' cambio a activo');
                }else{

                     //para obtener la colilla
                    $cliente_inter = Internet::where('id_cliente',$id_cliente)->where('activo',1)->get()->count();
                    $cliente_tv = Tv::where('id_cliente',$id_cliente)->where('activo',1)->get()->count();

                    if($cliente_inter>0 && $cliente_tv>0){
                        Cliente::where('id',$id_cliente)->update(['colilla'=>3]);

                    }elseif($cliente_inter>0){
                        Cliente::where('id',$id_cliente)->update(['colilla'=>1]);

                    }elseif($cliente_tv>0){
                        Cliente::where('id',$id_cliente)->update(['colilla'=>2]);

                    }else{
                        Cliente::where('id',$id_cliente)->update(['colilla'=>0]);

                    }

                    flash()->error("Error no es permitido tener 2 contratos del mismo tipo activos")->important();
                    return redirect()->route('clientes.contrato',$id_cliente);

                }
            }else{
                Internet::where('id',$id)->update(['activo'=>0]);
                Cliente::where('id',$id_cliente)->update(['internet'=>0]);
                $obj_controller_bitacora=new BitacoraController();	
                $obj_controller_bitacora->create_mensaje('Contrato: '.$num_contrato_inter.' cambio a inactivo');

            }
        }else{

            $tv = Tv::find($id);
            $id_cliente = $tv->id_cliente;
            $num_contrato_tv = $tv->numero_contrato;
            if($tv->activo==0 || $tv->activo==2){
                $tv_con = Tv::where('id_cliente',$id_cliente)->where('activo',1)->get()->count();

                if($tv_con==0){

                    Tv::where('id',$id)->update(['activo'=>1]);
                    Cliente::where('id',$id_cliente)->update(['tv'=>1]);
                    $obj_controller_bitacora=new BitacoraController();	
                    $obj_controller_bitacora->create_mensaje('Contrato: '.$num_contrato_tv.' cambio a activo');
                }else{

                     //para obtener la colilla
                    $cliente_inter = Internet::where('id_cliente',$id_cliente)->where('activo',1)->get()->count();
                    $cliente_tv = Tv::where('id_cliente',$id_cliente)->where('activo',1)->get()->count();

                    if($cliente_inter>0 && $cliente_tv>0){
                        Cliente::where('id',$id_cliente)->update(['colilla'=>3]);

                    }elseif($cliente_inter>0){
                        Cliente::where('id',$id_cliente)->update(['colilla'=>1]);

                    }elseif($cliente_tv>0){
                        Cliente::where('id',$id_cliente)->update(['colilla'=>2]);

                    }else{
                        Cliente::where('id',$id_cliente)->update(['colilla'=>0]);

                    }
                    flash()->error("Error no es permitido tener 2 contratos del mismo tipo activos")->important();
                    return redirect()->route('clientes.contrato',$id_cliente);

                }
                
            }else{
                Tv::where('id',$id)->update(['activo'=>0]);
                Cliente::where('id',$id_cliente)->update(['tv'=>0]);
                $obj_controller_bitacora=new BitacoraController();	
                $obj_controller_bitacora->create_mensaje('Contrato: '.$num_contrato_tv.' cambio a inactivo');

            }


        }

        //para obtener la colilla
        $cliente_inter = Internet::where('id_cliente',$id_cliente)->where('activo',1)->get()->count();
        $cliente_tv = Tv::where('id_cliente',$id_cliente)->where('activo',1)->get()->count();

        if($cliente_inter>0 && $cliente_tv>0){
            Cliente::where('id',$id_cliente)->update(['colilla'=>3]);

        }elseif($cliente_inter>0){
            Cliente::where('id',$id_cliente)->update(['colilla'=>1]);

        }elseif($cliente_tv>0){
            Cliente::where('id',$id_cliente)->update(['colilla'=>2]);

        }else{
            Cliente::where('id',$id_cliente)->update(['colilla'=>0]);

        }

        flash()->success("Estado cambiado exitosamente")->important();
        return redirect()->route('clientes.contrato',$id_cliente);


    }

    public function contrato_create($id){
        $correlativo_contra_tv = $this->correlativo(4,6);
        $correlativo_contra_inter = $this->correlativo(5,6);
        $cliente = Cliente::find($id);
        $inter_activos = Internet::where('id_cliente',$id)->where('activo',1)->get();
        $tv_activos = Tv::where('id_cliente',$id)->where('activo',1)->get();
        $velocidades = Velocidades::all();
        return view('contratos.create',compact('correlativo_contra_tv','correlativo_contra_inter','cliente','id','inter_activos','tv_activos','velocidades'));
        
    }
    public function contrato_store(Request $request){

        $id_cliente = $request->id_cliente;
        $codigo_cliente = $request->codigo;
        if($request->colilla==1){
            Cliente::where('id',$id_cliente)->update(['internet'=>1]);

            $internet = new Internet();
            $internet->id_cliente = $id_cliente;
            $internet->numero_contrato = $this->correlativo(5,6);
            if($request->fecha_instalacion!=""){
                $internet->fecha_instalacion = Carbon::createFromFormat('d/m/Y', $request->fecha_instalacion);

            }
            if($request->fecha_primer_fact!=""){
                $internet->fecha_primer_fact = Carbon::createFromFormat('d/m/Y', $request->fecha_primer_fact);

            }
            $cuota_mensual = explode(" ", $request->cuota_mensual);
            $internet->cuota_mensual = $cuota_mensual[1];

            if($request->costo_instalacion!=""){

                $costo_instalacion = explode(" ",$request->costo_instalacion);
                $internet->costo_instalacion = $costo_instalacion[1];
            }
            $internet->prepago = $request->prepago;
            $internet->dia_gene_fact = $request->dia_gene_fact;
            if($request->contrato_vence!=""){
                $internet->contrato_vence = Carbon::createFromFormat('d/m/Y', $request->contrato_vence);

            }
            $internet->periodo = $request->periodo;
            $internet->cortesia = $request->cortesia;
            $internet->velocidad = $request->velocidad;
            $internet->onu = $request->onu;
            $internet->onu_wifi = $request->onu_wifi;
            $internet->cable_red = $request->cable_red;
            $internet->router = $request->router;
            $internet->marca = $request->marca;
            $internet->modelo = $request->modelo;
            $internet->mac = $request->mac;
            $internet->serie = $request->serie;
            $internet->recepcion = $request->recepcion;
            $internet->trasmision = $request->trasmision;
            $internet->ip = $request->ip;
            $internet->identificador = 1;
            $internet->activo = 1;
            $internet->save();
            
            
            $obj_controller_bitacora=new BitacoraController();	
            $obj_controller_bitacora->create_mensaje('Se creo servicio de internet para el cliente: '.$codigo_cliente.' con numero de contrato: '.$this->correlativo(5,6));
            
            $this->setCorrelativo(5);

        }

        //si collilla es igual 2 se guarda en tabla tvs
        if($request->colilla==2){
            Cliente::where('id',$id_cliente)->update(['tv'=>1]);
            $tv = new Tv();
            $tv->id_cliente = $id_cliente;
            $tv->numero_contrato = $this->correlativo(4,6);
            if($request->fecha_instalacion_tv!=""){
                $tv->fecha_instalacion = Carbon::createFromFormat('d/m/Y', $request->fecha_instalacion_tv);

            }
            if($request->fecha_primer_fact_tv!=""){
                $tv->fecha_primer_fact = Carbon::createFromFormat('d/m/Y', $request->fecha_primer_fact_tv);

            }
            $cuota_mensual = explode(" ", $request->cuota_mensual_tv);
            $tv->cuota_mensual = $cuota_mensual[1];
            if($request->costo_instalacion_tv!=""){

                $costo_instalacion = explode(" ",$request->costo_instalacion_tv);
                $tv->costo_instalacion = $costo_instalacion[1];
            }
            
            $tv->prepago = $request->prepago_tv;
            $tv->dia_gene_fact = $request->dia_gene_fact_tv;
            if($request->contrato_vence_tv!=""){
                $tv->contrato_vence = Carbon::createFromFormat('d/m/Y', $request->contrato_vence_tv);

            }
            $tv->periodo = $request->periodo_tv;
            $tv->cortesia = $request->cortesia_tv;
            $tv->digital = $request->digital_tv;
            $tv->marca = $request->marca_tv;
            $tv->serie = $request->serie_tv;
            $tv->modelo = $request->modelo_tv;
            $tv->identificador = 2;
            $tv->activo = 1;
            $tv->save();
            
            $obj_controller_bitacora=new BitacoraController();	
            $obj_controller_bitacora->create_mensaje('Se creo servicio de tv para el cliente: '.$codigo_cliente.' con numero de contrato: '.$this->correlativo(4,6));
            
            $this->setCorrelativo(4);

        }

        //si collilla es igual a 3 va guarda en tabla tvs y internets
        if($request->colilla==3){
            Cliente::where('id',$id_cliente)->update(['internet'=>1, 'tv'=>1]);
            $internet = new Internet();
            $internet->id_cliente = $id_cliente;
            $internet->numero_contrato = $this->correlativo(5,6);
            if($request->fecha_instalacion!=""){
                $internet->fecha_instalacion = Carbon::createFromFormat('d/m/Y', $request->fecha_instalacion);

            }
            if($request->fecha_primer_fact!=""){
                $internet->fecha_primer_fact = Carbon::createFromFormat('d/m/Y', $request->fecha_primer_fact);

            }
            $cuota_mensual = explode(" ", $request->cuota_mensual);
            $internet->cuota_mensual = $cuota_mensual[1];
            $costo_instalacion = explode(" ",$request->costo_instalacion);
            $internet->costo_instalacion = $costo_instalacion[1];
            $internet->prepago = $request->prepago;
            $internet->dia_gene_fact = $request->dia_gene_fact;
            if($request->contrato_vence!=""){
                $internet->contrato_vence = Carbon::createFromFormat('d/m/Y', $request->contrato_vence);

            }
            $internet->periodo = $request->periodo;
            $internet->cortesia = $request->cortesia_tv;
            $internet->velocidad = $request->velocidad;
            $internet->onu = $request->onu;
            $internet->onu_wifi = $request->onu_wifi;
            $internet->cable_red = $request->cable_red;
            $internet->router = $request->router;
            $internet->marca = $request->marca;
            $internet->modelo = $request->modelo;
            $internet->mac = $request->mac;
            $internet->serie = $request->serie;
            $internet->recepcion = $request->recepcion;
            $internet->trasmision = $request->trasmision;
            $internet->ip = $request->ip;
            $internet->identificador = 1;
            $internet->activo = 1;
            $internet->save();
            
            $obj_controller_bitacora=new BitacoraController();	
            $obj_controller_bitacora->create_mensaje('Se creo servicio de internet para el cliente: '.$codigo_cliente.' con numero de contrato: '.$this->correlativo(5,6));
            
            $this->setCorrelativo(5);

            $tv = new Tv();
            $tv->id_cliente = $id_cliente;
            $tv->numero_contrato = $this->correlativo(4,6);
            if($request->fecha_instalacion_tv!=""){
                $tv->fecha_instalacion = Carbon::createFromFormat('d/m/Y', $request->fecha_instalacion_tv);

            }
            if($request->fecha_primer_fact_tv!=""){
                $tv->fecha_primer_fact = Carbon::createFromFormat('d/m/Y', $request->fecha_primer_fact_tv);

            }
            $cuota_mensual = explode(" ", $request->cuota_mensual_tv);
            $tv->cuota_mensual = $cuota_mensual[1];
            $costo_instalacion = explode(" ",$request->costo_instalacion_tv);
            $tv->costo_instalacion = $costo_instalacion[1];
            $tv->prepago = $request->prepago_tv;
            $tv->dia_gene_fact = $request->dia_gene_fact_tv;
            if($request->contrato_vence_tv!=""){
                $tv->contrato_vence = Carbon::createFromFormat('d/m/Y', $request->contrato_vence_tv);

            }
            $tv->periodo = $request->periodo_tv;
            $tv->cortesia = $request->cortesia_tv;
            $tv->digital = $request->digital_tv;
            $tv->marca = $request->marca_tv;
            $tv->serie = $request->serie_tv;
            $tv->modelo = $request->modelo_tv;
            $tv->identificador = 2;
            $tv->activo = 1;
            $tv->save();
            
            $obj_controller_bitacora=new BitacoraController();	
            $obj_controller_bitacora->create_mensaje('Se creo servicio de tv para el cliente: '.$codigo_cliente.' con numero de contrato: '.$this->correlativo(4,6));
            
            $this->setCorrelativo(4);
        }
        flash()->success("Contrato creados exitosamente!")->important();
        return redirect()->route('clientes.contrato',$id_cliente);

    }

    public function contrato_vista($id,$identificador){
        if($identificador==1){
            $this->contrato_internet($id);
        }
        if($identificador==2){
            $this->contrato_tv($id);

        }

    }

    private function contrato_internet($id){
        $contrato_internet= Internet::where('id',$id)->get();
        $id_cliente = $contrato_internet[0]->id_cliente;
        $cliente= Cliente::find($id_cliente);
        $n_contrato=Internet::where('id_cliente',$id_cliente)->count();

        //$fpdf = new FpdfClass('P','mm', 'Letter');
        $fpdf = new Rotation('P','mm', 'Letter');
        
        $fpdf->AliasNbPages();
        $fpdf->AddPage();
        $fpdf->Image('assets/images/logo.png',10,5,20,20); //(x,y,w,h)
        $fpdf->SetTitle('CONTRATOS | CABAL');

        $fpdf->SetTextColor(0,0,0);
        $fpdf->SetFont('Arial','',10);
        $fpdf->SetXY(66,10);
        $fpdf->cell(33,5,utf8_decode('Contrato N??'),0,0,'R');
        $fpdf->cell(50,5,$contrato_internet[0]->numero_contrato,1);
        $fpdf->SetXY(66,16);
        $fpdf->cell(33,5,utf8_decode('Cod. De Cliente'),0,0,'R');
        $fpdf->cell(50,5,$cliente->codigo,1);
        $fpdf->cell(32,5,'Cliente Nuevo',0,0,'R');
        $fpdf->cell(7,5,'Si',0);        
        $fpdf->SetFont('ZapfDingbats');
        if($n_contrato==1){
            $fpdf->cell(5,5,chr(52),1,0,'C');
            
        }else{
            $fpdf->cell(5,5,'',1,0,'C');

        }
        $fpdf->SetFont('Arial','',12);
        $fpdf->cell(7,5,'No',0);
        $fpdf->SetFont('ZapfDingbats');
        if($n_contrato!=1){
            $fpdf->cell(5,5,chr(52),1,0,'C');
            
        }else{
            $fpdf->cell(5,5,'',1,0,'C');

        }
        //TEXTO VERTICALL------------
        $fpdf->SetXY(10,150);
        $fpdf->SetFont('Arial','B',12);
        $fpdf->Rotate(90);
        $fpdf->cell(45,5,utf8_decode('Informaci??n de Cobro'),0);
        $fpdf->cell(26,5,'',0);
        $fpdf->cell(45,5,utf8_decode('Informaci??n Personal'),0);
        $fpdf->Rotate(0);
        //END TEXT VERTICAL-----------------
        $fpdf->SetXY(95,26);
        $fpdf->SetFont('Arial','',9);
        $fpdf->cell(7,5,'Nombre del cliente',0);
        $fpdf->SetXY(17,30);
        $fpdf->SetFont('Arial','',11);
        //$fpdf->SetTextColor(194,8,8);color rojo
        $fpdf->Cell(185,7,utf8_decode($cliente->nombre),1,0,'C');

        $fpdf->SetXY(17,37);
        $fpdf->Cell(20,5,'DUI',0,0,'C');
        $fpdf->SetFont('ZapfDingbats');
        $fpdf->cell(5,5,chr(52),1,1,'C');
        $fpdf->SetFont('Arial','',11);
        $fpdf->SetXY(17,42);
        $fpdf->Cell(20,5,'Pasaporte',0,0,'C');
        $fpdf->cell(5,5,'',1,0,'C');
        $fpdf->SetFont('Arial','',11);
        $fpdf->SetXY(44,37);
        $fpdf->Cell(30,10,$cliente->dui,1,0,'C');
        
        $fpdf->SetXY(74,40);
        $fpdf->Cell(10,5,'NIT',0,0,'C');
        $fpdf->Cell(60,5,$cliente->nit,1,1,'C');
        
        $fpdf->SetXY(17,48);
        $fpdf->Cell(20,5,'Masculino',0,0,'L');
        $fpdf->SetFont('ZapfDingbats');
        $fpdf->cell(5,5,chr(52),1,0,'C');
        $fpdf->SetFont('Arial','',11);
        $fpdf->Cell(20,5,'Femenino',0,0,'L');
        $fpdf->SetFont('ZapfDingbats');
        $fpdf->cell(5,5,chr(52),1,0,'C');
        $fpdf->SetFont('Arial','',11);
        $fpdf->Cell(30,5,'Soltero/a',0,0,'R');
        $fpdf->SetFont('ZapfDingbats');
        $fpdf->cell(5,5,chr(52),1,0,'C');
        $fpdf->SetFont('Arial','',11);
        $fpdf->Cell(20,5,'Casado/a',0,0,'R');
        $fpdf->SetFont('ZapfDingbats');
        $fpdf->cell(5,5,chr(52),1,0,'C');
        $fpdf->SetFont('Arial','',11);
        $fpdf->Cell(20,5,'Viudo/a',0,0,'R');
        $fpdf->SetFont('ZapfDingbats');
        $fpdf->cell(5,5,chr(52),1,0,'C');
        $fpdf->SetFont('Arial','',11);
        $fpdf->Cell(55,5,'Fecha de Nacimiento',0,2,'R');
        $fpdf->Cell(20,5,'',0,0,'R');
        $fpdf->Cell(25,5,$cliente->fecha_nacimiento->format('d-m-Y'),1,0,'R');
        if($cliente->id_municipio!=0){

            $direccion = $cliente->dirreccion.', '.$cliente->get_municipio->nombre.', '.$cliente->get_municipio->get_departamento->nombre;
        }else{
            $direccion = $cliente->dirreccion;
        }
        $direccion = substr($direccion,0,172);
        //$fpdf->MultiCell(158,5,utf8_decode($direccion));
        $fpdf->SetFont('Arial','',11);
        $fpdf->SetXY(17,58);
        $fpdf->cell(135,5,utf8_decode('Direcci??n domicilio'),1,0);
        $fpdf->cell(57,5,utf8_decode('Tel??fonos'),0,1,'C');
        $fpdf->SetXY(17,63);
        if($cliente->id_municipio!=0){

            $direccion = $cliente->dirreccion.', '.$cliente->get_municipio->nombre.', '.$cliente->get_municipio->get_departamento->nombre;
        }else{
            $direccion = $cliente->dirreccion;
        }
        $direccion = substr($direccion,0,60);
        $fpdf->cell(135,5,utf8_decode($direccion),1,0);
        $fpdf->cell(20,5,'Fijo',0,0,'C');
        $fpdf->cell(25,5,$cliente->telefono2,1,1,'L');
        $fpdf->SetXY(17,72);
        $fpdf->cell(135,5,utf8_decode('Direcci??n de trabajo'),1,0);
        $fpdf->cell(20,5,'Personal',0,0,'C');
        $fpdf->cell(25,5,$cliente->telefono1,1,0,'L');
        $fpdf->SetXY(17,77);
        if($cliente->id_municipio!=0){

            $direccion_cobro = $cliente->dirreccion_cobro.', '.$cliente->get_municipio->nombre.', '.$cliente->get_municipio->get_departamento->nombre;
        }else{
            $direccion_cobro = $cliente->dirreccion;
        }
        $direccion_cobro = substr($direccion_cobro,0,60);
        $fpdf->cell(135,5,utf8_decode($direccion_cobro),1,0);
        //$fpdf->TextWithDirection(110,50,'Text on Vertical','L');
        //$fpdf->SetFillColor(255, 215, 0);
        $fpdf->Rect(7, 26, 202 , 60, '');
        
        //----------------SEGUNDO CUADRO------------------------------------------------
        $fpdf->SetXY(17,90);
        $fpdf->SetFont('Arial','',9);
        $fpdf->cell(30,5,utf8_decode('Direcci??n de cobro'),0,0);
        $fpdf->cell(35,5,utf8_decode('La misma de Domicilio'),0);
        $fpdf->SetFont('ZapfDingbats');
        $fpdf->cell(5,5,chr(52),1,0,'C');
        $fpdf->SetFont('Arial','',9);
        $fpdf->cell(35,5,utf8_decode('La misma de Trabajo'),0);
        $fpdf->SetFont('ZapfDingbats');
        $fpdf->cell(5,5,chr(52),1,0,'C');

        
        $fpdf->SetFont('Arial','',11);
        $fpdf->SetXY(17,96);
        $fpdf->cell(135,5,utf8_decode('Otra:'),1,0);
        $fpdf->cell(57,5,utf8_decode('   WhatsApp'),0,1,'C');
        $fpdf->SetXY(17,101);
        $fpdf->cell(135,5,utf8_decode('Canton las Trancas lot el Pozo Ozatlan Usulutan'),1,0);
        $fpdf->cell(20,5,'',0,0,'C');
        $fpdf->cell(25,5,$cliente->telefono1,1,1,'L');
        $fpdf->SetXY(17,108);
        $fpdf->cell(20,5,utf8_decode('Email:'),0,0);
        $fpdf->cell(115,5,utf8_decode(''),1,0);
        $fpdf->cell(20,5,'',0,0,'C');
        $fpdf->cell(37,5,'   Zona',0,1,'L');
        $fpdf->SetFont('Arial','',10);
        $fpdf->SetXY(17,113);
        $fpdf->Multicell(135,5,utf8_decode('El cliente autoriza la entrega de su factura en formato electr??nico (ecofactura) por el siguiente medio: Email proporcionado por este formulario. Una copia de las condiciones de contrataci??n ser??n enviadas por este mismo medio.'),0);
        $fpdf->SetFont('Arial','',11);
        $fpdf->SetXY(172,115);
        $fpdf->cell(25,5,'Tamanique',1,0,'L');
        $fpdf->SetXY(17,130);
        $fpdf->cell(35,5,'Credito Fiscal',0);        
        $fpdf->SetFont('ZapfDingbats');
        if($cliente->tipo_documento==2){
            $fpdf->cell(5,5,chr(52),1,0,'C');
            
        }else{
            $fpdf->cell(5,5,'',1,0,'C');

        }
        $fpdf->SetFont('Arial','',12);
        $fpdf->cell(35,5,'Consumidor Final',0);
        $fpdf->SetFont('ZapfDingbats');
        if($cliente->tipo_documento==1){
            $fpdf->cell(5,5,chr(52),1,0,'C');
            
        }else{
            $fpdf->cell(5,5,'',1,0,'C');

        }

        $fpdf->SetFont('Arial','',10);
        $fpdf->SetXY(19,139);
        $fpdf->cell(67,5,utf8_decode('Persona Jur??dica - Raz??n/Denominaci??n'),0,0);
        $fpdf->cell(105,5,utf8_decode(''),1,1);
        $fpdf->SetXY(19,144);
        $fpdf->cell(40,5,utf8_decode('N??mero de Registro'),0);
        $fpdf->cell(40,5,$cliente->numero_registro,1,1);
        $fpdf->SetXY(19,149);
        $fpdf->cell(40,5,utf8_decode('NIT de Comercio'),0);
        $fpdf->cell(40,5,utf8_decode('7777-777777-777-7'),1,1);
        $fpdf->SetXY(19,157);
        $fpdf->cell(40,5,utf8_decode('Giro'),0,0);
        $fpdf->cell(145,5,utf8_decode($cliente->giro),1,1);
        $fpdf->Rect(17, 137, 190 , 26, '');
        $fpdf->Rect(7, 88, 202 , 77, '');
        //----------------------------------------------------------------
        $fpdf->SetFont('Arial','',8);
        $fpdf->SetXY(7,167);
        $fpdf->MultiCell(202,4,utf8_decode('Declaro que mi informaci??n personal es cierta y autorizo a las empresas a comprobar su autenticidad, previo a dar tr??mite a la solicitud de los servicios. Acepto que la documentaci??n donde conste la informaci??n proporcionada para efectos de este contrato, ser?? resguardada por la empresa. Doy fe de lo establecido en el presente formulario y anexos. Confirmo que he le??do y entiendo el contrato y las condiciones de la contrataci??n, las cuales fueron presentadas a mi persona por el personal de la empresa. Ratifico el contenido del mismo y firmo.'),0);

        $fpdf->SetFont('Arial','',9);
        $fpdf->SetXY(7,190);
        $fpdf->cell(42,5,'Firma del Vendedor',1,0,'C');
        $fpdf->Rect(7, 190, 42 , 17, '');
        $fpdf->SetFont('Arial','',8);
        $fpdf->SetXY(49,190);
        $fpdf->cell(110,5,'Nombre del Vendedor',0,0,'L');
        $fpdf->Rect(49, 190, 110 , 8, '');
        $fpdf->cell(53,5,utf8_decode('C??digo del Vendedor'),0,0,'C');
        $fpdf->SetXY(49,198);
        $fpdf->cell(110,5,'Lugar',0,0,'L');
        $fpdf->Rect(49, 198, 110 , 9, '');//Lugar
        $fpdf->Rect(159, 190, 50 , 8, '');//codigo de vendedor
        $fpdf->Rect(159, 198, 50 , 9, '');//fecha 
        $fpdf->cell(53,5,utf8_decode('Fecha'),0,1,'C');
        $fpdf->SetXY(7,207);
        $fpdf->cell(35,5,utf8_decode('Velocidades'),1,0,'C');
        $fpdf->cell(35,5,utf8_decode('Plazo de contrato'),1,0,'C');
        $fpdf->Rect(7, 207, 35 , 16, '');//cuadro de velocidades
        $fpdf->SetFont('Arial','',11);
        $fpdf->SetXY(7,215);
        $fpdf->cell(17,3,$contrato_internet[0]->velocidad,0,0,'C');
        $fpdf->cell(17,3,'5',0,0,'C');
        $fpdf->cell(9,3,'',0,0,'C');
        $fpdf->cell(17,3,$contrato_internet[0]->periodo,0,0,'C');
        $fpdf->SetXY(122,213);
        $fpdf->cell(17,3,'$ '.$contrato_internet[0]->cuota_mensual,0,0,'C');
        $fpdf->SetFont('Arial','',6);
        $fpdf->SetXY(7,220);
        $fpdf->cell(17,3,utf8_decode('Bajada'),1,0,'C');
        $fpdf->cell(18,3,utf8_decode('subida'),1,0,'C');
        $fpdf->cell(35,3,utf8_decode('Meses'),1,0,'C');
        $fpdf->Rect(42, 207, 35 , 16, '');//cuadro plazo de contrato
        $fpdf->SetXY(79,210);
        $fpdf->MultiCell(35,4,utf8_decode('Total de Servicios Mensuales Servicios Residenciales.'),0,'C');
        $fpdf->Rect(77, 207, 39 , 16, '');//cuadro de servicios mensuales
        $fpdf->SetXY(116,220);
        $fpdf->cell(32,3,utf8_decode('(IVA incluido)'),1,0,'C');
        $fpdf->Rect(116, 207, 32 , 16, '');//cuadro de iva incluido
        $fpdf->Rect(148, 207, 32 , 16, '');//recargo
        $fpdf->SetXY(148,208);
        $fpdf->SetFont('Arial','',8);
        $fpdf->MultiCell(32,4,utf8_decode('Cobro por pago tard??o                $2.99                Por servicio'),1,'C');
        $fpdf->SetFont('Arial','',6);
        $fpdf->SetXY(180,207);
        $fpdf->cell(29,3,utf8_decode('Firma de cliente'),1,0,'C');
        $fpdf->Rect(180, 207, 29 , 16, '');//cuadro de firma de cliente

        $fpdf->SetFont('Arial','B',10);
        $fpdf->SetXY(7,225);
        $fpdf->cell(135,5,utf8_decode('PAGARE SIN PROTESTO'),0,0,'L');
        $fpdf->cell(20,5,utf8_decode('POR US$'),0,0,'L');
        $fpdf->cell(40,5,$contrato_internet[0]->cuota_mensual*$contrato_internet[0]->periodo,1,1,'L');
        $fpdf->SetXY(7,230);
        $fpdf->SetFont('Arial','',8);
        $fpdf->cell(195,5,utf8_decode('                                                                                                      (Ciudad),                             de                                                           de 2021'),0,1,'L');
        $fpdf->SetXY(7,235);
        $fpdf->cell(195,5,utf8_decode('de_______________________de 20 _______ la cantidad de _____________________________________ D??LARES DE LOS ESTADOS UNIDOS DE        '),0,1,'L');        
        $fpdf->SetXY(7,240);
        $fpdf->cell(195,5,utf8_decode('AM??RICA, m??s el inter??s convencional del UNO PUNTO CERO CERO por ciento mensual, calculados a partir de la fecha de suscripci??n del presente'),0,1,'L');        
        $fpdf->SetXY(7,245);
        $fpdf->cell(195,5,utf8_decode('documento, pagar??(mos) adem??s a partir de esta ??ltima fecha, intereses moratorios sobre el saldo de capital de mora.'),0,1,'L');
        $fpdf->SetXY(7,254);
        $fpdf->SetFont('Arial','B',10);
        $fpdf->cell(120,5,'Firma del Suscriptor:_________________________',0,0,'L');
        $fpdf->cell(75,5,'Firma del Avalista:_____________________',0,1,'L');
        /*$fpdf->SetXY(15,113);
        if(isset($contrato_internet[0]->contrato_vence)==1){
            $contrato_vence = $contrato_internet[0]->contrato_vence->format('d/m/Y');
        }else{
            $contrato_vence ="";
        }*/
        //$fpdf->cell(40,10,utf8_decode('FECHA INICIO DE CONTRATO: '.$fecha_instalacion.'    FINALIZACI??N DEL CONTRATO: '.$contrato_vence));
        /*
        if($contrato_internet[0]->onu==1){
            $fpdf->cell(10,5,chr(52),1,1,'C');
            
        }else{
            $fpdf->cell(10,5,'',1,1,'C');

        }*/
        //--------------------------------------------------------------------------------------------------------------------
        //--------------------------------------------------------------------------------------------------------------------
        $fpdf->AliasNbPages();
        $fpdf->AddPage();
        // Logo
        $fpdf->Image('assets/images/logo.png',10,5,20,20); //(x,y,w,h)
        $fpdf->SetFont('Arial','',12);
        $fpdf->SetXY(64,10);
        $fpdf->Cell(20,5,$cliente->nombre);
        

        $fpdf->SetXY(10,30);
        $fpdf->SetFont('Arial','',8);
        //$fpdf->SetTextColor(0,0,0);
        $fpdf->Multicell(195,5,utf8_decode('1. OBJETO. REDES LITORALES, S.A. de C.V. (en adelante REDES LITORALES) identificada indistintamente como la empresa, quien prestar?? los servicios navegaci??n por Internet de forma inal??mbrica, acceso dedicado a Internet, y cualesquiera otros servicios suplementarios y/o de valor agregado prestados a trav??s de las redes e infraestructura de telecomunicaciones con las que cuenten la empresa. Los servicios se prestan al CLIENTE en su condici??n de destinatario o usuario final de los mismos, y con base a las estipulaciones ahora establecidas en este documento o cualquiera de sus anexos.'),0);
        //$fpdf->SetXY(7,50);
        $fpdf->Multicell(195,5,utf8_decode(' 2. TARIFAS, FACTURACI??N Y PAGO. El CLIENTE abonar?? a la empresa las cantidades resultantes de la prestaci??n de cada uno de los servicios contratados, seg??n las tarifas contratadas con el usuario. Adicionalmente, el CLIENTE cancelar?? las cantidades resultantes por cualquier servicio suplementario y/o de valor agregado utilizado, y las tarifas, de traslados, desinstalaciones y desconexiones. Los pagos ser??n facturados en d??lares de Estados Unidos de Am??rica, por per??odos mensuales, acorde a los sistemas de facturaci??n utilizados por la empresa. El CLIENTE deber?? pagar las facturas en las fechas estipuladas en las mismas, dichos pagos podr??n realizarse en cualquiera de los siguientes lugares de acuerdo con preferencia y disponibilidad: Centros de Servicio CABAL, instituciones bancarias y/o financieras y/o cualquier otro medio autorizado al efecto por la empresa. Se aplicar?? el cargo por pago tard??o correspondiente para cada servicio, seg??n el monto estipulado en el contrato. Las facturas reflejar??n los conceptos por los servicios utilizados. La falta de recepci??n de la factura no inhibe la obligaci??n de pago derivada de la prestaci??n de los servicios siendo responsabilidad del cliente notificar a la empresa dicha situaci??n. La empresa, previa autorizaci??n del cliente, podr?? entregar facturas por cualquier medio electr??nico. FACTURACI??N ELECTRONICA: El env??o de factura por medio electr??nico se regir?? bajo las siguientes condiciones: (a) El env??o de factura por medio electr??nico exime a la empresa de enviar la factura f??sica a la direcci??n de correspondencia del CLIENTE. (b) La empresa expedir??n la factura por medio electr??nico de acuerdo con sus procesos internos y una vez emitida la enviar?? por el o los medios electr??nicos seleccionados por el CLIENTE, quien ser?? responsable de la verificaci??n mensual de la recepci??n de la misma. (c) El CLIENTE reconoce, entiende y acepta que es su responsabilidad contar con la disponibilidad de recibir la facturaci??n por los medios electr??nicos por ??l seleccionados (d) En el caso de que El CLIENTE no reciba la factura en los medios designados, no se eximir?? su responsabilidad de pago, y adem??s deber?? de notificar a la empresa dicha situaci??n (e) En el caso que El CLIENTE designe m??s de un medio, la empresa dar?? prioridad al correo electr??nico.'),0);
        $fpdf->Multicell(195,5,utf8_decode('3. DURACI??N Y TERMINACI??N. El plazo de los servicios contratados se establecer?? en el contrato, o sus anexos para cada servicio, pudiendo acordarse por las partes plazo obligatorio o se podr?? celebrar sin plazo obligatorio y por lo tanto por tiempo indefinido, contado su vigencia partir del d??a de instalaci??n, activaci??n, uso y/o programaci??n de cada uno de los servicios, y hasta que el cliente decida darlo de baja.'),0);
        $fpdf->Multicell(195,5,utf8_decode('3.1. DERECHO A DARSE DE BAJA: El CLIENTE tiene derecho a darse de baja de conformidad a lo establecido en el art??culo 13-8 de la Ley de Protecci??n al Consumidor, para hacer uso de tal derecho EL CLIENTE deber??, comunicarlo a la empresa con una antelaci??n de diez (10) d??as h??biles al momento en que haya de surtir efectos, en el caso de darse de baja antes del vencimiento del plazo obligatorio deber?? cancelar la penalidad establecida en la cl??usula quince.'),0);
        $fpdf->Multicell(195,5,utf8_decode('3.2. CONTINUIDAD DE LOS SERVICIOS: Una vez finalizado el plazo obligatorio pactado o cuando no exista plazo obligatorio pactado por las partes, sin que el cliente solicite la baja, los servicios se continuar??n prestando de forma indefinida, pudiendo el cliente darse de baja en cualquier momento notific??ndolo con una antelaci??n de diez (10) d??as h??biles al momento en que haya de surtir efectos la baja, sin incurrir en ninguna penalidad. '),0);
        $fpdf->Multicell(195,5,utf8_decode(' 3.3. TERMINACI??N GENERICA: El plazo obligatorio pactado tambi??n podr?? terminarse por: Las causas generales de extinci??n de los contratos, las establecidas en la ley, as?? mismo la empresa tendr??n derecho a darlo por terminado en los siguientes casos: a) cuando el CLIENTE utilice indebidamente los servicios, efect??e fraude de telecomunicaciones o haga uso il??cito de los servicios b) Cuando el CLIENTE se encuentre en mora por m??s de dos meses (2) consecutivos, y c) Cuando EL CLIENTE solicite la prestaci??n de servicios en lugares distintos a los establecidos en el contrato o en lugares en los que la empresa se vea imposibilitada f??sica o t??cnicamente para proveer los mismos. La terminaci??n de la prestaci??n de los servicios no eximir?? al CLIENTE de sus obligaciones frente a la empresa por los bienes y servicios recibidos. EL CLIENTE acepta que la empresa podr?? notificarle sobre la finalizaci??n del plazo obligatorio de este contrato a trav??s de los medios t??cnicos de acuerdo con disponibilidad y conveniencia.'),0);
        $fpdf->Multicell(195,5,utf8_decode(' 4. ACTIVACI??N, PROPIEDAD DE LOS EQUIPOS Y FUNCIONAMIENTO DEL SERVICIO. Los servicios ser??n prestados ??nicamente a trav??s de los accesos, n??meros y/o habilitaciones que la empresa entregue o efect??e a favor del CLIENTE. Los equipos proporcionados para proveer los servicios contratados se entender??n entregados a EL CLIENTE en la calidad en que se indique en el contrato. Los equipos entregados en calidad de comodato y/o arrendamiento son propiedad de la empresa, Los equipos entregados a EL CLIENTE en concepto de compraventa, donaci??n o venta a plazos son propiedad del CLIENTE siempre y cuando se cumplan las condiciones pactadas en el presente instrumento o sus anexos. La entrega de los equipos, dependiendo del servicio, se realizar?? a trav??s de la emisi??n de una orden de entrega y/o instalaci??n, la cual ser?? firmada por el usuario, o en su defecto por la persona que se encuentre en el domicilio de instalaci??n se??alado por el CLIENTE.'),0);
        
        $fpdf->AliasNbPages();
        $fpdf->AddPage();
        // Logo
        $fpdf->Image('assets/images/logo.png',10,5,20,20); //(x,y,w,h)
        $fpdf->SetFont('Arial','',10);
        $fpdf->SetXY(66,10);
        $fpdf->cell(33,5,utf8_decode('Contrato N??'),0,0,'R');
        $fpdf->cell(50,5,$contrato_internet[0]->numero_contrato,1);
        $fpdf->SetXY(66,16);
        $fpdf->cell(33,5,utf8_decode('Cod. De Cliente'),0,0,'R');
        $fpdf->cell(50,5,$cliente->codigo,1);
        

        $fpdf->SetXY(10,30);
        $fpdf->SetFont('Arial','',8);
        $fpdf->Multicell(195,4,utf8_decode('En caso de hurto, robo o p??rdida de los equipos, es responsabilidad del CLIENTE notificar a la empresa para el inmediato bloqueo de los servicios y se deber?? presentar la denuncia correspondiente ante las autoridades competentes, haciendo llegar una copia certificada de la denuncia a cualquiera de los Centros de Servicio de la empresa dentro de los siguientes cinco. (5) d??as de su interposici??n. La p??rdida, destrucci??n, deterioro, robo, hurto o extrav??o de los equipos no inhibe la obligaci??n de pago derivada de la prestaci??n de los servicios que se encuentren disponibles para El CLIENTE. S. GARANT??A COMERCIAL. Los equipos provistos para la prestaci??n de los servicios gozar??n de la garant??a otorgada por sus fabricantes por lo que estar??n sujetos a los t??rminos y condiciones del producto adquirido; salvo que se disponga lo contrario en un documento especial anexo al presente contrato.'),0);
        $fpdf->Multicell(195,4,utf8_decode('6. CALIDAD, COBERTURA Y MANTENIMIENTO DEL SERVICIO. La empresa prestar?? sus servicios conforme a los niveles de calidad, par??metros y m??todos establecidos en la legislaci??n vigente y las condiciones de este contrato y/o cualquiera de sus anexos. En cualquier supuesto, el usuario reconoce especialmente que cuenta con toda la informaci??n relativa a la cobertura y calidad de los servicios prestados. La empresa prestar?? el servicio exclusivamente en las zonas de cobertura dentro del territorio nacional en el que el mismo est?? implantado, seg??n la informaci??n de cobertura de cada servicio disponible en cualquiera de los Centros de Servicio de la empresa. El CLIENTE entiende que los servicios m??viles y servicios de internet podr??n verse afectados por condiciones estructurales, orogr??ficas y/o atmosf??ricas, manipulaci??n inadecuada por el usuario, cantidad de usuarios conectados, o cualquier otra situaci??n que impidan, imposibiliten o degraden su prestaci??n en cuyo caso se actuar?? conforme a lo establecido en la legislaci??n vigente. En el caso de los servicios residenciales EL CLIENTE reconoce que los mismos ser??n prestados en la direcci??n y domicilio proporcionado al momento de la contrataci??n. La empresa dar?? mantenimiento preventivo y correctivo de sus infraestructuras, equipos y/o redes para la prestaci??n de los servicios, de manera que, si se produjeran aver??as, interrupciones o mal funcionamiento de los servicios por causas fortuitas o de fuerza mayor, la empresa reparar?? en el plazo m??s breve posible los fallos o aver??as producidos. La empresa no ser?? responsable por da??os en la configuraci??n y/o funcionamiento en los equipos propiedad del usuario, una vez que el servicio haya sido instalado/activado a su entera satisfacci??n.'),0);
        $fpdf->Multicell(195,4,utf8_decode('7. SUSPENSI??N TEMPORAL DE LOS SERVICIOS. La empresa podr?? suspender la prestaci??n de los servicios, incluso el plazo de vigencia pactado, sin previo aviso en los casos establecidos en la legislaci??n vigente. La empresa, previo aviso a EL CLIENTE, podr??n suspender los servicios en los siguientes casos: indicio de fraude de telecomunicaciones, uso il??cito del servicio o, mora en el pago de los servicios del cliente. Cuando la empresa deba hacer una suspensi??n general, temporal y continua de cualquiera de los servicios, la empresa har?? los avisos correspondientes de acuerdo a la ley; en estos casos ??stas no ser??n responsables por los da??os y perjuicios ocasionados por la suspensi??n, ni ser?? motivo de incumplimiento contractual. El CLIENTE acepta, la limitaci??n de uso de cualquiera de los servicios y tendr?? lugar cuando haya excedido el l??mite de cr??dito, cuando tenga facturas pendientes de pago o cuando se haya solicitado el bloqueo de servicios de suplementarios y/o de valor agregado. '),0);
        $fpdf->Multicell(195,4,utf8_decode('8. L??MITE DE CR??DITO. La empresa pone a disposici??n del CLIENTE un cr??dito de consumo por cada servicio contratado, el cual ser?? determinado y, posteriormente modificado a solicitud del CLIENTE, con base a su comportamiento crediticio. '),0);
        $fpdf->Multicell(195,4,utf8_decode('9. GARANT??AS DE PAGO. El CLIENTE pone a disposici??n de cada prestador del servicio, un t??tulo valor que respaldar?? el pago de las cantidades resultantes de cualquiera de los servicios utilizados y el valor total de los equipos provistos. El t??tulo valor ser?? devuelto, a solicitud del cliente, dentro de los quince (15) d??as h??biles siguientes a la fecha efectiva de terminaci??n de todos los servicios, previa cancelaci??n de todas las obligaciones pendientes de pago. Adicionalmente a lo anterior, el CLIENTE pondr?? a disposici??n de la empresa cualquier otro tipo de garant??as para la Formaci??n y ejecuci??n de este contrato, las cuales estar??n contenidas en documentos anexos a este instrumento. '),0);
        $fpdf->Multicell(195,4,utf8_decode('10. SECRETO DE LAS COMUNICACIONES. La empresa adoptar?? las medidas t??cnicas y organizativas necesarias conforme a la legislaci??n vigente a fin de garantizar el secreto de las telecomunicaciones. En cualquier caso, la empresa quedar?? exoneradas de toda responsabilidad derivada de la obtenci??n ilegal de material y/o informaci??n, de su uso o publicidad por terceros, y en general, de cuantas acciones u omisiones, incluso de entidades gubernamentales, que, no siendo imputables a la empresa, supongan un quebrantamiento del secreto de las comunicaciones. '),0);
        $fpdf->Multicell(195,4,utf8_decode('11. CESI??N. EL CLIENTE no podr?? ceder el presente contrato, ni los servicios adquiridos, a terceros sin el consentimiento escrito previo de la empresa. La empresa podr?? ceder el contrato a las sociedades filiales y/o subsidiarias pertenecientes a la empresa.'),0);
        $fpdf->Multicell(195,4,utf8_decode('12. INFORMACI??N DE LOS SERVICIOS. La empresa facilitar?? al CLIENTE la informaci??n necesaria y conveniente para una adecuada prestaci??n del servicio, la cual podr?? ser suministrada en medios de comunicaci??n social, Centros de Servicio, Centro de Llamadas, en su p??gina web, puntos de venta o comercializadores autorizados. En caso de que el CLIENTE solicite informaci??n particular sobre la prestaci??n de los servicios, la empresa se las proporcionar?? sin cargo alguno. Asimismo, el CLIENTE acepta recibir informaci??n respecto de los productos y servicios contratados a trav??s de medios t??cnicos tales como correo electr??nico, telefax, mensajes cortos, televisi??n, tel??fono y cualquier otra tecnolog??a que permita la comunicaci??n a distancia. El CLIENTE establece como medio de notificaci??n, las direcciones f??sicas y electr??nicas, n??meros de tel??fono y dem??s datos proporcionados en el presente contrato.'),0);
        $fpdf->Multicell(195,4,utf8_decode('13. VALIDACI??N DE MEDIOS T??CNICOS. Las partes reconocen la validez de los datos y procedimientos electr??nicos y/o telem??ticos para la prestaci??n e incorporaci??n de servicios. El cliente consiente expresamente que podr??n utilizarse los medios t??cnicos para expresar el consentimiento del CLIENTE para la modificaci??n, incorporaci??n y/o supresi??n de servicios disponibles incluyendo servicios de valor agregado. El CLIENTE es responsable de la custodia diligente y el mantenimiento de la confidencialidad de su informaci??n, as?? como de las contrase??as, claves de acceso, sistemas cifrados o sistemas de encriptaci??n de comunicaci??n, que sean facilitados por la empresa, dichos mecanismos se entender??n que son utilizados ??nicamente por El CLIENTE.'),0);
        $fpdf->Multicell(195,4,utf8_decode('14. MODIFICACI??N DEL CONTRATO. EL CLIENTE podr?? realizar adiciones o mejoras de los servicios contratados, acept??ndolos trav??s de cualquiera de los medios establecidos en la cl??usula trece (13) de este instrumento; una vez aceptados se entender?? estar de acuerdo en los t??rminos, condiciones, precio y plazo de los mismos.'),0);
        $fpdf->Multicell(195,4,utf8_decode('15. PENALIDADES. La empresa podr?? establecer penalidades por terminaci??n anticipada, uso indebido o ilegal de los servicios, incumplimiento del contrato y otras causales que conforme a la legislaci??n salvadore??a sean aplicables. En caso de terminaci??n anticipada del plazo obligatorio el CLIENTE, este deber?? de pagar a la empresa, el valor de los bienes y servicios recibidos a la fecha efectiva de terminaci??n, a su vez El CLIENTE cancelar?? en'),0);
        
        $fpdf->AliasNbPages();
        $fpdf->AddPage();
        // Logo
        $fpdf->Image('assets/images/logo.png',10,5,20,20); //(x,y,w,h)
        $fpdf->SetFont('Arial','',10);
        $fpdf->SetXY(66,10);
        $fpdf->cell(33,5,utf8_decode('Contrato N??'),0,0,'R');
        $fpdf->cell(50,5,$contrato_internet[0]->numero_contrato,1);
        $fpdf->SetXY(66,16);
        $fpdf->cell(33,5,utf8_decode('Cod. De Cliente'),0,0,'R');
        $fpdf->cell(50,5,$cliente->codigo,1);
        $fpdf->cell(32,5,'Cliente Nuevo',0,0,'R');
        $fpdf->cell(7,5,'Si',0);        
        $fpdf->SetFont('ZapfDingbats');
        if($n_contrato==1){
            $fpdf->cell(5,5,chr(52),1,0,'C');
            
        }else{
            $fpdf->cell(5,5,'',1,0,'C');

        }
        $fpdf->SetFont('Arial','',12);
        $fpdf->cell(7,5,'No',0);
        $fpdf->SetFont('ZapfDingbats');
        if($n_contrato!=1){
            $fpdf->cell(5,5,chr(52),1,0,'C');
            
        }else{
            $fpdf->cell(5,5,'',1,0,'C');

        }


        $fpdf->SetXY(10,30);
        $fpdf->SetFont('Arial','',8);
        $fpdf->Multicell(195,4,utf8_decode('concepto de penalidad a la empresa una cantidad equivalente al da??o producido por la terminaci??n anticipada del contrato, el cual ser?? un monto equivalente al ochenta por ciento (80%) de los cargos b??sicos de los servicios que hacen falta para la terminaci??n del contrato; si al cliente se le entregaron dispositivos en calidad distinta a una venta a plazo, se actuar?? de acuerdo al anexo de equipos.'),0);
        $fpdf->Multicell(195,4,utf8_decode('16. ATENCI??N AL CLIENTE. Existe a disposici??n del CLIENTE, en cualquiera de los Centros de Servicio y Centro de Llamadas, un ??rea de atenci??n a la que podr?? dirigir peticiones de informaci??n sobre los servicios contratados.'),0);
        $fpdf->Multicell(195,4,utf8_decode('17. ATENCION A RECLAMOS. El CLIENTE podr?? presentar sus reclamos relacionados con la prestaci??n de los servicios, en aquellos Centros de Servicios que para tal efecto dispongan la empresa. Cuando la reclamaci??n haya sido solucionada, la empresa informar?? al CLIENTE de la soluci??n adoptada a trav??s de los medios designados por el CLIENTE.'),0);
        $fpdf->Multicell(195,4,utf8_decode('18. LEGISLACI??N APLICABLE Y DOMICILIO. Las partes acuerdan que las presentes condiciones generales y/o sus anexos se sujetan a la legislaci??n de la Rep??blica de El Salvador, y los conflictos derivados de los mismos se sujetan a las reglas de jurisdicci??n y competencia de la legislaci??n com??n.'),0);
        
        $fpdf->SetXY(10,80);
        $fpdf->Multicell(195,4,utf8_decode('SERVICIO DE INTERNET RESIDENCIAL. El servicio de Internet ser?? prestado bajo las siguientes condiciones: 1) El CLIENTE utilizar?? el servicio ??nicamente desde el n??mero de protocolo de interconexi??n asignado por la empresa y los requerimientos t??cnicos de los equipos del CLIENTE que se indiquen al efecto; 2) De forma continua, las veinticuatro horas del d??a, todos los d??as del a??o, durante la vigencia del per??odo contratado para la prestaci??n del servicio, a la velocidad establecida en el plan seleccionado. EL CLIENTE entiende que la velocidad de navegaci??n depende de diversos factores t??cnicos, tales como: caracter??sticas y cantidad de los equipos conectados a la red, incompatibilidad con otras tecnolog??a o software utilizadas por el cliente, entre otros similares, y en caso de proceder compensaci??n se actuar?? de conformidad a la ley vigente 3) El CLIENTE garantizar?? las instalaciones el??ctricas, equipos de protecci??n asociados y el equipo inform??tico adecuado para acceder al servicio. 4) Bajo la legislaci??n nacional e internacional vigente que velan por los derechos de propiedad intelectual, protecci??n de menores, protecci??n de datos y otras; el uso y/o descarga de contenido ilegal puede constituir infracci??n a tales legislaciones; en consecuencia, la empresa por requerimiento administrativo o judicial se encuentran obligadas a revelar la informaci??n de la titularidad de la conexi??n de donde se originaron los usos indebidos a la red. En estos casos, el CLIENTE asume toda la responsabilidad derivada del uso indebido del servicio; 5) El CLIENTE podr?? en cualquier tiempo mejorar el servicio contratado a trav??s de los diversos medios t??cnicos puestos a disposici??n por la empresa.*'),0);
        $fpdf->Cell(195,10,utf8_decode('ANEXO DE EQUIPOS'),0,1);
        $fpdf->Multicell(195,4,utf8_decode('Para efectos de la prestaci??n de los servicios descritos en las condiciones generales de contrataci??n se entender??n como equipos, modems, routers, cajas decodificadoras, entre otros necesarios para las comunicaciones. La empresa podr?? poner a disposici??n del CLIENTE seg??n las condiciones contratadas y seleccionadas los equipos acordes a las siguientes estipulaciones: l. CONDICIONES DEL COMODATO. La empresa ser??n las propietarias de los equipos entregados al CLIENTE para la prestaci??n de los servicios, y el CLIENTE se obliga a mantener la debida diligencia y el cuidado de los mismos; siendo ??ste ??ltimo responsable de las aver??as resultantes cuando le fueren imputables; caso contrario, la empresa deber??n sustituir los bienes a solicitud del CLIENTE y cuando a criterio de la empresa se requiera. Asimismo, en caso de restituci??n de equipos, por ejemplo,'),0);
        $fpdf->Multicell(195,4,utf8_decode('por terminaci??n contractual, EL CLIENTE deber?? devolverlos a la empresa en un plazo m??ximo de diez (10) d??as h??biles; caso contrario, EL CLIENTE reconoce que deber?? cancelar el valor del mercado de los equipos al momento de la contrataci??n. En caso de que el CLIENTE contrate servicios residenciales, se entregar??n en calidad de comodato los siguientes equipos: caja digital principal, Cable Modem, y Router. La empresa podr?? disponer forma distinta de adquirir el equipo conforme a las observaciones descritas en el contrato. 11. CONDICIONES DEL ARRENDAMIENTO. El valor de los equipos entregados en concepto de arrendamiento ser?? determinado, en el caso que aplicare, en las condiciones comerciales o de promoci??n. El CLIENTE reconoce que los equipos son propiedad de la empresa y deber??n ser restituidos al finalizar la relaci??n contractual. Durante el tiempo que dure el arrendamiento, el CLIENTE se obliga a mantener la debida diligencia y cuido en los mismos, siendo ??ste responsable de la p??rdida, extrav??o, destrucci??n, aver??as, robo, hurto u otras causas resultantes cuando le fueren imputables. La empresa podr?? disponer forma distinta de adquirir el equipo conforme a las observaciones descritas en el contrato. El Cliente deber?? de cancelar el costo administrativo determinado por LA EMPRESA por el retiro de los equipos. 111. CONDICIONES DE LA DONACI??N SUJETA A CONDICI??N. Los equipos entregados sin costo para EL CLIENTE, se entender??n gratuitos siempre y cuando este ??ltimo cumpla tanto con los t??rminos y condiciones generales de contrataci??n, as?? como el plazo de vigencia obligatorio. El CLIENTE se obliga a mantener la debida diligencia y cuido de los equipos, siendo ??ste responsable de la p??rdida, extrav??o, destrucci??n, aver??as, robo, hurto u otras causas resultantes cuando le fueren imputables. En caso de terminaci??n anticipada del contrato, EL CLIENTE se obliga a pagar a la empresa el valor de mercado del equipo al tiempo de la contrataci??n, adem??s de las penalidades a que hubiere lugar, de conformidad a lo establecido en las condiciones de este contrato. IV. PLAZO: El plazo del comodato y/o arrendamiento y/o venta a plazos ser?? el acordado seg??n las condiciones comerciales, de promoci??n o las establecidas en el presente instrumento. V. ENTREGA DE LOS EQUIPOS. En este acto, el CLIENTE acepta y recibe a satisfacci??n los equipos en perfecto estado de funcionamiento, por haber sido revisados y puestos a su disposici??n, previo a su entrega material.'),0);
        $fpdf->Output();
        exit;

    }

    private function contrato_tv($id){
        $contrato_internet= Tv::where('id',$id)->get();
        $id_cliente = $contrato_internet[0]->id_cliente;
        $cliente= Cliente::find($id_cliente);

        $fpdf = new FpdfClass('P','mm', 'Letter');
        
        $fpdf->AliasNbPages();
        $fpdf->AddPage();
        $fpdf->SetTitle('CONTRATOS | UNINET');

        $fpdf->SetXY(175,22);
        $fpdf->SetFont('Arial','',15);
        $fpdf->SetTextColor(194,8,8);
        $fpdf->Cell(30,10,$contrato_internet[0]->numero_contrato);
        $fpdf->SetTextColor(0,0,0);
        $fpdf->SetFont('Arial','B',12);
        $fpdf->SetXY(65,26);
        $fpdf->cell(30,10,utf8_decode('CONTRATO DE SERVICIO DE TELEVISI??N'));
        //$contrato_internet[0]->numero_contrato
        $fpdf->SetXY(165,22);
        $fpdf->SetFont('Arial','',14);
        $fpdf->SetTextColor(194,8,8);
        $fpdf->Cell(30,10,utf8_decode('N??.'));
        $fpdf->SetTextColor(0,0,0);

        $fpdf->SetFont('Arial','',11);
        
        $fpdf->SetXY(15,30);
        $fpdf->cell(40,10,utf8_decode('Servicio No: '.$contrato_internet[0]->numero_contrato));
        $fpdf->SetXY(38,30);
        $fpdf->cell(40,10,'_________');

        $fpdf->SetXY(156,30);
        if(isset($contrato_internet[0]->fecha_instalacion)==1){
            $fecha_instalacion = $contrato_internet[0]->fecha_instalacion->format('d/m/Y');
        }else{
            $fecha_instalacion ="";
        }
        $fpdf->cell(30,10,utf8_decode('Fecha: '.$fecha_instalacion));
        $fpdf->SetXY(169,30);
        $fpdf->cell(40,10,'______________');

        $fpdf->SetXY(15,36);
        $fpdf->cell(40,10,utf8_decode('NOMBRE COMPLETO: '.$cliente->nombre));
        $fpdf->SetXY(57,36);
        $fpdf->cell(40,10,'__________________________________________________________________');

        $fpdf->SetXY(15,42);
        $fpdf->cell(40,10,utf8_decode('DUI: '.$cliente->dui));
        $fpdf->SetXY(24,42);
        $fpdf->cell(40,10,'______________');

        $fpdf->SetXY(85,42);
        $fpdf->cell(40,10,utf8_decode('NIT: '.$cliente->nit));
        $fpdf->SetXY(93,42);
        $fpdf->cell(40,10,'______________');

        $fpdf->SetXY(153,42);
        $fpdf->cell(40,10,utf8_decode('TEL: '.$cliente->telefono1));
        $fpdf->SetXY(163,42);
        $fpdf->cell(40,10,'_________________');

        $fpdf->SetXY(15,48);
        $fpdf->cell(40,10,utf8_decode('DIRRECCI??N:'));
        $fpdf->SetXY(44,50);
        $fpdf->SetFont('Arial','',11);
        if($cliente->id_municipio!=0){

            $direccion = $cliente->dirreccion.', '.$cliente->get_municipio->nombre.', '.$cliente->get_municipio->get_departamento->nombre;
        }else{
            $direccion = $cliente->dirreccion;
        }
        $direccion = substr($direccion,0,172);
        $fpdf->MultiCell(158,5,utf8_decode($direccion));
        $fpdf->SetXY(42,48);
        $fpdf->SetFont('Arial','',11);
        $fpdf->cell(40,10,'_________________________________________________________________________');
        $fpdf->SetXY(42,53);
        $fpdf->cell(40,10,'_________________________________________________________________________');


        $fpdf->SetXY(15,59);
        $fpdf->cell(40,10,utf8_decode('CORREO ELECTRONICO: '.$cliente->email));
        $fpdf->SetXY(62,59);
        $fpdf->cell(40,10,'________________________________________________________________');

        $fpdf->SetFont('Arial','B',12);
        $fpdf->SetXY(89,65);
        $fpdf->cell(30,10,utf8_decode('OCUPACI??N'));

        $fpdf->SetFont('Arial','',11);

        $fpdf->SetXY(15,71);
        $fpdf->cell(30,10,utf8_decode('EMPLEADO'));
        $fpdf->SetXY(42,73);
        $fpdf->SetFont('ZapfDingbats');
        if($cliente->ocupacion==1){

            $fpdf->cell(10,5,chr(52),1,1,'C');
        }else{
            $fpdf->cell(10,5,'',1,1,'C');
        }

        $fpdf->SetFont('Arial','',12);

        $fpdf->SetXY(57,71);
        $fpdf->cell(30,10,utf8_decode('COMERCIANTE'));
        $fpdf->SetXY(92,73);
        $fpdf->SetFont('ZapfDingbats');
        if($cliente->ocupacion==2){
            
            $fpdf->cell(10,5,chr(52),1,1,'C');
        }else{
            $fpdf->cell(10,5,'',1,1,'C');

        }

        $fpdf->SetFont('Arial','',12);

        $fpdf->SetXY(107,71);
        $fpdf->cell(30,10,utf8_decode('INDEPENDIENTE'));
        $fpdf->SetXY(145,73);
        $fpdf->SetFont('ZapfDingbats');
        if($cliente->ocupacion==3){

            $fpdf->cell(10,5,chr(52),1,1,'C');
        }else{

            $fpdf->cell(10,5,'',1,1,'C');

        }

        $fpdf->SetFont('Arial','',12);

        $fpdf->SetXY(160,71);
        $fpdf->cell(30,10,utf8_decode('OTROS'));
        $fpdf->SetXY(178,73);
        $fpdf->SetFont('ZapfDingbats');
        if($cliente->ocupacion==4){
            $fpdf->cell(10,5,chr(52),1,1,'C');
            
        }else{
            $fpdf->cell(10,5,'',1,1,'C');

        }

        $fpdf->SetFont('Arial','',11);

        $fpdf->SetXY(15,77);
        $fpdf->cell(30,10,utf8_decode('CONDICI??N ACTUAL DEL LUGAR DE LA PRESTACI??N DEL SERVICIO'));

        $fpdf->SetFont('Arial','',11);

        $fpdf->SetXY(15,83);
        $fpdf->cell(30,10,utf8_decode('CASA PROPIA'));
        $fpdf->SetXY(47,85);
        $fpdf->SetFont('ZapfDingbats');
        if($cliente->condicion_lugar==1){
            $fpdf->cell(10,5,chr(52),1,1,'C');
            
        }else{
            $fpdf->cell(10,5,'',1,1,'C');

        }

        $fpdf->SetFont('Arial','',11);

        $fpdf->SetXY(60,83);
        $fpdf->cell(30,10,utf8_decode('ALQUILADA'));
        $fpdf->SetXY(87,85);
        $fpdf->SetFont('ZapfDingbats');
        if($cliente->condicion_lugar==2){
            $fpdf->cell(10,5,chr(52),1,1,'C');
            
        }else{
            $fpdf->cell(10,5,'',1,1,'C');

        }

        $fpdf->SetFont('Arial','',11);

        $fpdf->SetXY(100,83);
        $fpdf->cell(30,10,utf8_decode('OTROS'));
        $fpdf->SetXY(119,85);
        $fpdf->SetFont('ZapfDingbats');
        if($cliente->condicion_lugar==3){
            $fpdf->cell(10,5,chr(52),1,1,'C');
            
        }else{
            $fpdf->cell(10,5,'',1,1,'C');

        }

        $fpdf->SetFont('Arial','',11);

        $fpdf->SetXY(15,89);
        $fpdf->cell(40,10,utf8_decode('NOMBRE DEL DUE??O DEL INMUEBLE: '.$cliente->nombre_dueno));
        $fpdf->SetXY(88,89);
        $fpdf->cell(40,10,'___________________________________________________');

        $fpdf->SetFont('Arial','B',12);
        $fpdf->SetXY(70,95);
        $fpdf->cell(30,10,utf8_decode('SERVICIOS CONTRATADOS'));

        $fpdf->SetFont('Arial','',11);

        $fpdf->SetXY(15,101);
        $fpdf->cell(40,10,utf8_decode('CANALES: '));
        $fpdf->SetXY(39,101);
        $fpdf->cell(40,10,utf8_decode('_____________ TELEVISI??N'));

        $fpdf->SetXY(95,103);
        $fpdf->SetFont('ZapfDingbats');
        $fpdf->cell(10,5,chr(52),1,1,'C');

        $fpdf->SetFont('Arial','',11);
        $fpdf->SetXY(112,101);
        $fpdf->cell(40,10,'$ '.$contrato_internet[0]->cuota_mensual);
        $fpdf->SetXY(114,101);
        $fpdf->cell(40,10,'_______ TOTAL MENSUAL $ '.$contrato_internet[0]->cuota_mensual);
        $fpdf->SetXY(165,101);
        $fpdf->cell(40,10,'_______');

        $fpdf->SetXY(15,107);
        $fpdf->cell(40,10,utf8_decode('COSTOS POR INSTALACI??N $ '.$contrato_internet[0]->costo_instalacion));
        $fpdf->SetXY(68,107);
        $fpdf->cell(40,10,'__________ (PRECIO INCLUYE IVA)');

        $fpdf->SetXY(15,113);
        if(isset($contrato_internet[0]->contrato_vence)==1){
            $contrato_vence = $contrato_internet[0]->contrato_vence->format('d/m/Y');
        }else{
            $contrato_vence ="";
        }
        $fpdf->cell(40,10,utf8_decode('FECHA INICIO DE CONTRATO: '.$fecha_instalacion.'    FINALIZACI??N DEL CONTRATO: '.$contrato_vence));
        $fpdf->SetXY(72,113);
        $fpdf->cell(40,10,'___________                                                        __________');

        $fpdf->SetFont('Arial','',10);
        $fpdf->SetXY(15,121);
        $fpdf->MultiCell(186,5,utf8_decode('El presente contrato es una plaza de '.$contrato_internet[0]->periodo.' meses a partir de la fecha de instalaci??n del servicio por escrito y pudiendo prorrogarse con el consentimiento del mismo. Si el cliente desea dar por finalizada la relaci??n del servicio debe comunicarse a TECNNITEL con quince d??as de anticipaci??n.'));
        $fpdf->SetFont('Arial','B',11);
        $fpdf->SetXY(60,135);
        $fpdf->cell(40,10,utf8_decode('PENALIDAD POR TERMINACI??N ANTICIPADA'));
        $fpdf->SetFont('Arial','',10);
        $fpdf->SetXY(15,142);
        $fpdf->MultiCell(186,5,utf8_decode('Si el cliente desea dar por terminado el presente contrato de este servicio de manera anticipada por voluntad propia, se ver?? en la obligaci??n de cancelar todos los meses pendientes del plazo contratado por el mismo valor y hacer la entrega de los aparatos y accesorios que fueron entregados al cliente en COMODATO que TECNNITEL ha proporcionado para la prestaci??n de estos servicios. La protecci??n de estos componentes queda bajo la responsabilidad del cliente quien responder?? por da??os o extrav??os de los equipos entregados. En caso de da??o o extrav??o el cliente deber?? cancelar su valor econ??mico a TECNNITEL. Si hubiere un elemento con falla por causa de fabricaci??n: TECNNITEL lo reemplazar?? previa recuperaci??n del elemento da??ado.'));
        
        $fpdf->SetFont('Arial','',11);

        $fpdf->SetXY(55,176);
        $fpdf->cell(30,10,utf8_decode('ONU'));
        $fpdf->SetXY(69,178);
        $fpdf->SetFont('ZapfDingbats');
        if($contrato_internet[0]->onu==1){
            $fpdf->cell(10,5,chr(52),1,1,'C');
            
        }else{
            $fpdf->cell(10,5,'',1,1,'C');

        }

        $fpdf->SetFont('Arial','',12);

        $fpdf->SetXY(126,176);
        $fpdf->cell(30,10,utf8_decode('ONU + CATV'));
        $fpdf->SetXY(155,178);
        $fpdf->SetFont('ZapfDingbats');
        if($contrato_internet[0]->onu_wifi==1){
            $fpdf->cell(10,5,chr(52),1,1,'C');
            
        }else{
            $fpdf->cell(10,5,'',1,1,'C');

        }

        $fpdf->SetFont('Arial','',12);

        $fpdf->SetXY(33,182);
        $fpdf->cell(30,10,utf8_decode('CABLE DE RED'));
        $fpdf->SetXY(69,184);
        $fpdf->SetFont('ZapfDingbats');
        if($contrato_internet[0]->cable_red==1){
            $fpdf->cell(10,5,chr(52),1,1,'C');
            
        }else{
            $fpdf->cell(10,5,'',1,1,'C');

        }

        $fpdf->SetFont('Arial','',12);

        $fpdf->SetXY(133,182);
        $fpdf->cell(30,10,utf8_decode('ROUTER'));
        $fpdf->SetXY(155,184);
        $fpdf->SetFont('ZapfDingbats');
        if($contrato_internet[0]->router==1){
            $fpdf->cell(10,5,chr(52),1,1,'C');
            
        }else{
            $fpdf->cell(10,5,'',1,1,'C');

        }

        $fpdf->SetFont('Arial','',10);
        $fpdf->SetXY(15,190);
        $fpdf->MultiCell(186,5,utf8_decode('El presente contrato de servicio contiene los t??rminos y las condiciones de contrataci??n de TECNNITEL los cuales he recibido de parte del mismo en este acto, y constituyen los aplicables de manera general a la prestaci??n de SERVICIO DE INTERNET presentados por TECNNITEL.'));
        
        $fpdf->SetFont('Arial','B',11);
        $fpdf->SetXY(33,203);
        $fpdf->cell(40,10,utf8_decode('CONDICIONES APLICABLES AL SERVICIO DE DIFUSI??N POR SUSCRIPCI??N'));
        //segunda pagina
        $fpdf->SetFont('Arial','',10);
        $fpdf->SetXY(15,211);
        $fpdf->MultiCell(186,5,utf8_decode('Es mediante el cual TECNNITEL  se obliga a prestar al cliente por medio de fibra ??ptica: el servicio de DIFUSI??N POR SUSCRIPCI??N que ser?? prestado de forma continua las veinticuatro horas y los trescientos sesenta y cinco d??as del a??o durante la vigencia del presente contrato: salvo en caso de mora por parte del cliente o por caso fortuito o de fuerza mayor. En caso de que exista interrupci??n del servicio de cualquier ??ndole t??cnica y que perdure como un m??ximo de veinticuatro horas el cliente deber?? ser recompensado con un descuento aplicado a la pr??xima factura TECNNITEL no es responsable por causa que no est??n bajo su control, y que con lleven en alguna interrupci??n en el servicio de transmisi??n de la se??al.
Este contrato no incluye servicios adicionales como el servicio de PAGAR POR VER (PPV)'));
        $fpdf->SetXY(15,252);
        $fpdf->SetFont('Arial','',9);
        $fpdf->MultiCell(186,5,utf8_decode('1. OBLIGACIONES ESPECIALES DEL CLIENTE CON RELACI??N AL SERVICIO DE DIFUSI??N POR SUSCRIPCI??N: El cliente se obliga especialmente. A) a no manipular la fibra ??ptica en ning??n otro equipo ya que su ruptura ocasionara el corte de la se??al y sub distribuir el servicio a terceras personas B) No conectar equipos adicionales a los consignados en este contrato. C) No alterar, remover ni cambiar total o parcialmente el equipo o los elementos entregados para la prestaci??n de este servicio. D) No contratar ni permitir que personas no autorizadas por TECNNITEL, realicen labores de reparaci??n en los equipos. E), EL cliente autoriza a TECNNITEL el sitio a Instalar los equipos y componentes necesarios para la prestaci??n del servicio, 2. CARGOS ESPECIALES Y TARIFAS EN CASO DE MORA: el cliente acepta que en caso de mora que exceda los diez d??as por falta de pago TECNNITEL suspender?? el servicio; la reconexi??n se har?? efectiva una vez el cliente cancele la deuda en su totalidad m??s la cancelaci??n de tres d??lares Americanos en concepto de cargo por rehabilitaci??n de servicio. 3. CARGOS Y TARIFAS EN CASO DE TRASLADO DEL DOMICILIO DEL SERVICIO DE DIFUSI??N POR SUSCRIPCI??N: En caso de traslado de domicilio el cliente deber?? notificar inmediatamente a TECNNITEL para programar la reconexi??n del servicio en el nuevo domicilio, entendiendo que el nuevo domicilio deber?? estar dentro de la red de cobertura del servicio de TECNNITEL. Un cargo de quince d??lares deber?? ser cancelado por el cliente correspondiente a reconexi??n por traslado de domicilio, valor que se har?? por anticipado. LIMITACIONES Y RESTRICCIONES DE MATERIAL PARA PROVEER DEL SERVICIO DE DIFUSI??N POR SUSCRIPCI??N.
        PAGO DE CUOTA: El cliente se compromete a pagar la cuota mensual y puntual ??nicamente en la oficina de TECNNITEL seg??n la fecha de contrataci??n.'));
        // Logo

        $fecha_instalacion = $contrato_internet[0]->fecha_instalacion;
        if($fecha_instalacion!=""){
            $corte_fecha = explode("-", $fecha_instalacion);

            $corte_dia = explode(" ", $corte_fecha[2]);
        }else{
            $corte_dia=["","",""];
            $corte_fecha=["","",""];
        }
        $fpdf->Image('assets/images/LOGO.png',15,83,60,25); //(x,y,w,h)
        $fpdf->SetXY(120,86);
        $fpdf->SetFont('Arial','B',18);
        $fpdf->cell(40,10,utf8_decode('PAGAR?? SIN PROTESTO'));

        $fpdf->SetFont('Arial','',12);
        $fpdf->SetXY(143,92);
        $fpdf->cell(40,10,utf8_decode('Apopa '.$corte_dia[0].' de '.$this->spanishMes($corte_fecha[1]).' de '.$corte_fecha[0].'.'));

        $fpdf->SetXY(168,98);
        $fpdf->cell(40,10,utf8_decode('Por: U$ '.($contrato_internet[0]->cuota_mensual*$contrato_internet[0]->periodo)));

        $fpdf->SetFont('Arial','',10);
        $fpdf->SetXY(15,106);
        $fpdf->MultiCell(186,5,utf8_decode('POR ESTE PAGAR??, YO, '.$cliente->nombre.', me obligo a pagar incondicionalmente a TECNNITEL, la cantidad de '.($contrato_internet[0]->cuota_mensual*$contrato_internet[0]->periodo).' U$ D??lares, reconociendo, en caso de mora, el inter??s del (DIEZ%) 10 por ciento mensual sobre saldo Insoluto. 
La suma antes mencionada la pagar?? en esta ciudad, en las oficinas principales de TECNNITEL, el d??a '.$corte_dia[0].' de '.$this->spanishMes($corte_fecha[1]).' del a??o '.$corte_fecha[0]).'.');

        $fpdf->SetXY(15,132);
        $fpdf->MultiCell(186,5,utf8_decode('En caso de acci??n jur??dica y de ejecuci??n, se??alo la ciudad de apopa como domicilio especial, siendo a mi cargo, cualquier gasto que la sociedad acreedora antes mencionada hiciere en el cobro de la presente obligaci??n, inclusive los llamados personales y facult?? a la sociedad para que designe al depositario judicial de los bienes que se me embarguen a quien revelo de la obligaci??n.'));
        $fpdf->SetXY(50,150);
        $fpdf->SetFont('Arial','B',11);
        $fpdf->cell(40,10,utf8_decode('DUI: '.$cliente->dui).'                       NIT: '.$cliente->nit);
        $fpdf->SetXY(110,158);
        $fpdf->SetFont('Arial','',11);
        $fpdf->cell(40,10,utf8_decode('FIRMA DEL CLIENTE: ______________________'));

        $fpdf->SetFont('Arial','B',11);
        $fpdf->SetXY(26,166);
        $fpdf->cell(40,10,utf8_decode('TERMINOS Y CONTRATACIONES GENERALES DE CONTRATACI??N DE TECNNITEL'));
        $fpdf->SetXY(15,174);
        $fpdf->SetFont('Arial','',10);
        $fpdf->MultiCell(186,5,utf8_decode('Los terminos y condiciones indicados en el mismo por parte de TECNNITEL de Nacionalidad Salvadore??a de este domicilio, en adelante denominada "EI PROVEEDOR". Las condiciones particulares en cuanto a plazo, tarifas y especificaciones de equipo para la prestaci??n de servicios a cada CLIENTE, se encuentran todas detalladas en el presente CONTRATO DE SERVICIO que El CLIENTE suscribe con EI PROVEEDOR, los cuales forman parte Integrante del presente documento CONDICIONES GENERAL APLICABLES 1. PLAZO; el plazo obligatorio de vigencia aplicable a la prestaci??n de los servicios del proveedor que entrar?? en vigencia se estipula en el presente contrato que El CLIENTE suscribe con EL PROVEEDOR y contar?? a partir de la fecha de suscripci??n. Una vez transcurrido el plazo obligatorio antes indicado, el plazo de contrato de cada servicio continuar?? por tiempo indefinido TERMINACION: anticipada; en caso de que EL CLIENTE solicite la terminaci??n dentro del plazo obligatorio ant Indicado, deber?? pagar a El PROVEEDOR, todos y cada unos de los cargos pendientes del pago a la fecha de terminaci??n efectiva del servicio de que se traten y adem??s le obliga a pagar en concepto de penalidad por terminaci??n anticipadas las cantidades se??aladas en El CONTRATO DE SERVICIO que corresponda. B) Suspensi??n por mora EL PROVEEDOR podr?? suspender cualquiera de los servicios contratados por Incumplimientos de las obligaciones EI CLIENTE este podr?? dar por terminado el plazo de vigencia del presente CONTRATO DE SERVICIO que corresponda.'));
        $fpdf->SetXY(38,249);
        //$fpdf->setFillColor(0,0,0); 
        //$fpdf->SetTextColor(255,255,255);
        $fpdf->SetFont('Arial','B',11);
        $fpdf->MultiCell(135,5,utf8_decode('Direcci??n: Centro comercial Pericentro Local 22 Apopa, San Salvador
        Correo Electronico: atencion@uninet.com.sv'),1,'C',0);
        $fpdf->SetTextColor(0,0,0);


        //----------------------------------------------------------------------------------------------------------------------
        //----------------------------------------------------------------------------------------------------------------------


        $fpdf->AliasNbPages();
        $fpdf->AddPage();
        //$fpdf->SetTitle('CONTRATOS | UNINET');

        // Logo
        $fpdf->Image('assets/images/LOGO.png',10,5,60,25); //(x,y,w,h)
        // Arial bold 15
        $fpdf->SetFont('Arial','B',22);
        // Movernos a la derecha
        $fpdf->SetXY(80,10);
        // T??tulo
        $fpdf->Cell(30,10,'TECNNITEL S.A de C.V.');
        $fpdf->SetXY(81,16);
        $fpdf->SetFont('Arial','',12);
        $fpdf->Cell(30,10,'SERVICIO DE TELECOMUNICACIONES');

        $fpdf->SetXY(180,10);
        $fpdf->SetFont('Arial','B',10);
        $fpdf->Cell(20,8,'COPIA',1,1,'C');

        $fpdf->SetXY(175,22);
        $fpdf->SetFont('Arial','',15);
        $fpdf->SetTextColor(194,8,8);
        $fpdf->Cell(30,10,$contrato_internet[0]->numero_contrato);
        $fpdf->SetTextColor(0,0,0);
        $fpdf->SetFont('Arial','B',12);
        $fpdf->SetXY(65,26);
        $fpdf->cell(30,10,utf8_decode('CONTRATO DE SERVICIO DE TELEVISI??N'));
        //$contrato_internet[0]->numero_contrato
        $fpdf->SetXY(165,22);
        $fpdf->SetFont('Arial','',14);
        $fpdf->SetTextColor(194,8,8);
        $fpdf->Cell(30,10,utf8_decode('N??.'));
        $fpdf->SetTextColor(0,0,0);

        $fpdf->SetFont('Arial','',11);
        
        $fpdf->SetXY(15,30);
        $fpdf->cell(40,10,utf8_decode('Servicio No: '.$contrato_internet[0]->numero_contrato));
        $fpdf->SetXY(38,30);
        $fpdf->cell(40,10,'_________');

        $fpdf->SetXY(156,30);
        if(isset($contrato_internet[0]->fecha_instalacion)==1){
            $fecha_instalacion = $contrato_internet[0]->fecha_instalacion->format('d/m/Y');
        }else{
            $fecha_instalacion ="";
        }
        $fpdf->cell(30,10,utf8_decode('Fecha: '.$fecha_instalacion));
        $fpdf->SetXY(169,30);
        $fpdf->cell(40,10,'______________');

        $fpdf->SetXY(15,36);
        $fpdf->cell(40,10,utf8_decode('NOMBRE COMPLETO: '.$cliente->nombre));
        $fpdf->SetXY(57,36);
        $fpdf->cell(40,10,'__________________________________________________________________');

        $fpdf->SetXY(15,42);
        $fpdf->cell(40,10,utf8_decode('DUI: '.$cliente->dui));
        $fpdf->SetXY(24,42);
        $fpdf->cell(40,10,'______________');

        $fpdf->SetXY(85,42);
        $fpdf->cell(40,10,utf8_decode('NIT: '.$cliente->nit));
        $fpdf->SetXY(93,42);
        $fpdf->cell(40,10,'______________');

        $fpdf->SetXY(153,42);
        $fpdf->cell(40,10,utf8_decode('TEL: '.$cliente->telefono1));
        $fpdf->SetXY(163,42);
        $fpdf->cell(40,10,'_________________');

        $fpdf->SetXY(15,48);
        $fpdf->cell(40,10,utf8_decode('DIRRECCI??N:'));
        $fpdf->SetXY(44,50);
        $fpdf->SetFont('Arial','',11);
        if($cliente->id_municipio!=0){

            $direccion = $cliente->dirreccion.', '.$cliente->get_municipio->nombre.', '.$cliente->get_municipio->get_departamento->nombre;
        }else{
            $direccion = $cliente->dirreccion;
        }
        $direccion = substr($direccion,0,172);
        $fpdf->MultiCell(158,5,utf8_decode($direccion));
        $fpdf->SetXY(42,48);
        $fpdf->SetFont('Arial','',11);
        $fpdf->cell(40,10,'_________________________________________________________________________');
        $fpdf->SetXY(42,53);
        $fpdf->cell(40,10,'_________________________________________________________________________');


        $fpdf->SetXY(15,59);
        $fpdf->cell(40,10,utf8_decode('CORREO ELECTRONICO: '.$cliente->email));
        $fpdf->SetXY(62,59);
        $fpdf->cell(40,10,'________________________________________________________________');

        $fpdf->SetFont('Arial','B',12);
        $fpdf->SetXY(89,65);
        $fpdf->cell(30,10,utf8_decode('OCUPACI??N'));

        $fpdf->SetFont('Arial','',11);

        $fpdf->SetXY(15,71);
        $fpdf->cell(30,10,utf8_decode('EMPLEADO'));
        $fpdf->SetXY(42,73);
        $fpdf->SetFont('ZapfDingbats');
        if($cliente->ocupacion==1){

            $fpdf->cell(10,5,chr(52),1,1,'C');
        }else{
            $fpdf->cell(10,5,'',1,1,'C');
        }

        $fpdf->SetFont('Arial','',12);

        $fpdf->SetXY(57,71);
        $fpdf->cell(30,10,utf8_decode('COMERCIANTE'));
        $fpdf->SetXY(92,73);
        $fpdf->SetFont('ZapfDingbats');
        if($cliente->ocupacion==2){
            
            $fpdf->cell(10,5,chr(52),1,1,'C');
        }else{
            $fpdf->cell(10,5,'',1,1,'C');

        }

        $fpdf->SetFont('Arial','',12);

        $fpdf->SetXY(107,71);
        $fpdf->cell(30,10,utf8_decode('INDEPENDIENTE'));
        $fpdf->SetXY(145,73);
        $fpdf->SetFont('ZapfDingbats');
        if($cliente->ocupacion==3){

            $fpdf->cell(10,5,chr(52),1,1,'C');
        }else{

            $fpdf->cell(10,5,'',1,1,'C');

        }

        $fpdf->SetFont('Arial','',12);

        $fpdf->SetXY(160,71);
        $fpdf->cell(30,10,utf8_decode('OTROS'));
        $fpdf->SetXY(178,73);
        $fpdf->SetFont('ZapfDingbats');
        if($cliente->ocupacion==4){
            $fpdf->cell(10,5,chr(52),1,1,'C');
            
        }else{
            $fpdf->cell(10,5,'',1,1,'C');

        }

        $fpdf->SetFont('Arial','',11);

        $fpdf->SetXY(15,77);
        $fpdf->cell(30,10,utf8_decode('CONDICI??N ACTUAL DEL LUGAR DE LA PRESTACI??N DEL SERVICIO'));

        $fpdf->SetFont('Arial','',11);

        $fpdf->SetXY(15,83);
        $fpdf->cell(30,10,utf8_decode('CASA PROPIA'));
        $fpdf->SetXY(47,85);
        $fpdf->SetFont('ZapfDingbats');
        if($cliente->condicion_lugar==1){
            $fpdf->cell(10,5,chr(52),1,1,'C');
            
        }else{
            $fpdf->cell(10,5,'',1,1,'C');

        }

        $fpdf->SetFont('Arial','',11);

        $fpdf->SetXY(60,83);
        $fpdf->cell(30,10,utf8_decode('ALQUILADA'));
        $fpdf->SetXY(87,85);
        $fpdf->SetFont('ZapfDingbats');
        if($cliente->condicion_lugar==2){
            $fpdf->cell(10,5,chr(52),1,1,'C');
            
        }else{
            $fpdf->cell(10,5,'',1,1,'C');

        }

        $fpdf->SetFont('Arial','',11);

        $fpdf->SetXY(100,83);
        $fpdf->cell(30,10,utf8_decode('OTROS'));
        $fpdf->SetXY(119,85);
        $fpdf->SetFont('ZapfDingbats');
        if($cliente->condicion_lugar==3){
            $fpdf->cell(10,5,chr(52),1,1,'C');
            
        }else{
            $fpdf->cell(10,5,'',1,1,'C');

        }

        $fpdf->SetFont('Arial','',11);

        $fpdf->SetXY(15,89);
        $fpdf->cell(40,10,utf8_decode('NOMBRE DEL DUE??O DEL INMUEBLE: '.$cliente->nombre_dueno));
        $fpdf->SetXY(88,89);
        $fpdf->cell(40,10,'___________________________________________________');

        $fpdf->SetFont('Arial','B',12);
        $fpdf->SetXY(70,95);
        $fpdf->cell(30,10,utf8_decode('SERVICIOS CONTRATADOS'));

        $fpdf->SetFont('Arial','',11);

        $fpdf->SetXY(15,101);
        $fpdf->cell(40,10,utf8_decode('CANALES: '));
        $fpdf->SetXY(39,101);
        $fpdf->cell(40,10,utf8_decode('_____________ TELEVISI??N'));

        $fpdf->SetXY(95,103);
        $fpdf->SetFont('ZapfDingbats');
        $fpdf->cell(10,5,chr(52),1,1,'C');

        $fpdf->SetFont('Arial','',11);
        $fpdf->SetXY(112,101);
        $fpdf->cell(40,10,'$ '.$contrato_internet[0]->cuota_mensual);
        $fpdf->SetXY(114,101);
        $fpdf->cell(40,10,'_______ TOTAL MENSUAL $ '.$contrato_internet[0]->cuota_mensual);
        $fpdf->SetXY(165,101);
        $fpdf->cell(40,10,'_______');

        $fpdf->SetXY(15,107);
        $fpdf->cell(40,10,utf8_decode('COSTOS POR INSTALACI??N $ '.$contrato_internet[0]->costo_instalacion));
        $fpdf->SetXY(68,107);
        $fpdf->cell(40,10,'__________ (PRECIO INCLUYE IVA)');

        $fpdf->SetXY(15,113);
        if(isset($contrato_internet[0]->contrato_vence)==1){
            $contrato_vence = $contrato_internet[0]->contrato_vence->format('d/m/Y');
        }else{
            $contrato_vence ="";
        }
        $fpdf->cell(40,10,utf8_decode('FECHA INICIO DE CONTRATO: '.$fecha_instalacion.'    FINALIZACI??N DEL CONTRATO: '.$contrato_vence));
        $fpdf->SetXY(72,113);
        $fpdf->cell(40,10,'___________                                                        __________');

        $fpdf->SetFont('Arial','',10);
        $fpdf->SetXY(15,121);
        $fpdf->MultiCell(186,5,utf8_decode('El presente contrato es una plaza de '.$contrato_internet[0]->periodo.' meses a partir de la fecha de instalaci??n del servicio por escrito y pudiendo prorrogarse con el consentimiento del mismo. Si el cliente desea dar por finalizada la relaci??n del servicio debe comunicarse a TECNNITEL con quince d??as de anticipaci??n.'));
        $fpdf->SetFont('Arial','B',11);
        $fpdf->SetXY(60,135);
        $fpdf->cell(40,10,utf8_decode('PENALIDAD POR TERMINACI??N ANTICIPADA'));
        $fpdf->SetFont('Arial','',10);
        $fpdf->SetXY(15,142);
        $fpdf->MultiCell(186,5,utf8_decode('Si el cliente desea dar por terminado el presente contrato de este servicio de manera anticipada por voluntad propia, se ver?? en la obligaci??n de cancelar todos los meses pendientes del plazo contratado por el mismo valor y hacer la entrega de los aparatos y accesorios que fueron entregados al cliente en COMODATO que TECNNITEL ha proporcionado para la prestaci??n de estos servicios. La protecci??n de estos componentes queda bajo la responsabilidad del cliente quien responder?? por da??os o extrav??os de los equipos entregados. En caso de da??o o extrav??o el cliente deber?? cancelar su valor econ??mico a TECNNITEL. Si hubiere un elemento con falla por causa de fabricaci??n: TECNNITEL lo reemplazar?? previa recuperaci??n del elemento da??ado.'));
        
        $fpdf->SetFont('Arial','',11);

        $fpdf->SetXY(55,176);
        $fpdf->cell(30,10,utf8_decode('ONU'));
        $fpdf->SetXY(69,178);
        $fpdf->SetFont('ZapfDingbats');
        if($contrato_internet[0]->onu==1){
            $fpdf->cell(10,5,chr(52),1,1,'C');
            
        }else{
            $fpdf->cell(10,5,'',1,1,'C');

        }

        $fpdf->SetFont('Arial','',12);

        $fpdf->SetXY(126,176);
        $fpdf->cell(30,10,utf8_decode('ONU + CATV'));
        $fpdf->SetXY(155,178);
        $fpdf->SetFont('ZapfDingbats');
        if($contrato_internet[0]->onu_wifi==1){
            $fpdf->cell(10,5,chr(52),1,1,'C');
            
        }else{
            $fpdf->cell(10,5,'',1,1,'C');

        }

        $fpdf->SetFont('Arial','',12);

        $fpdf->SetXY(33,182);
        $fpdf->cell(30,10,utf8_decode('CABLE DE RED'));
        $fpdf->SetXY(69,184);
        $fpdf->SetFont('ZapfDingbats');
        if($contrato_internet[0]->cable_red==1){
            $fpdf->cell(10,5,chr(52),1,1,'C');
            
        }else{
            $fpdf->cell(10,5,'',1,1,'C');

        }

        $fpdf->SetFont('Arial','',12);

        $fpdf->SetXY(133,182);
        $fpdf->cell(30,10,utf8_decode('ROUTER'));
        $fpdf->SetXY(155,184);
        $fpdf->SetFont('ZapfDingbats');
        if($contrato_internet[0]->router==1){
            $fpdf->cell(10,5,chr(52),1,1,'C');
            
        }else{
            $fpdf->cell(10,5,'',1,1,'C');

        }

        $fpdf->SetFont('Arial','',10);
        $fpdf->SetXY(15,190);
        $fpdf->MultiCell(186,5,utf8_decode('El presente contrato de servicio contiene los t??rminos y las condiciones de contrataci??n de TECNNITEL los cuales he recibido de parte del mismo en este acto, y constituyen los aplicables de manera general a la prestaci??n de SERVICIO DE INTERNET presentados por TECNNITEL.'));
        
        $fpdf->SetFont('Arial','B',11);
        $fpdf->SetXY(33,203);
        $fpdf->cell(40,10,utf8_decode('CONDICIONES APLICABLES AL SERVICIO DE DIFUSI??N POR SUSCRIPCI??N'));
        //segunda pagina
        $fpdf->SetFont('Arial','',10);
        $fpdf->SetXY(15,211);
        $fpdf->MultiCell(186,5,utf8_decode('Es mediante el cual TECNNITEL  se obliga a prestar al cliente por medio de fibra ??ptica: el servicio de DIFUSI??N POR SUSCRIPCI??N que ser?? prestado de forma continua las veinticuatro horas y los trescientos sesenta y cinco d??as del a??o durante la vigencia del presente contrato: salvo en caso de mora por parte del cliente o por caso fortuito o de fuerza mayor. En caso de que exista interrupci??n del servicio de cualquier ??ndole t??cnica y que perdure como un m??ximo de veinticuatro horas el cliente deber?? ser recompensado con un descuento aplicado a la pr??xima factura TECNNITEL no es responsable por causa que no est??n bajo su control, y que con lleven en alguna interrupci??n en el servicio de transmisi??n de la se??al.
Este contrato no incluye servicios adicionales como el servicio de PAGAR POR VER (PPV)'));
        $fpdf->SetXY(15,252);
        $fpdf->SetFont('Arial','',9);
        $fpdf->MultiCell(186,5,utf8_decode('1. OBLIGACIONES ESPECIALES DEL CLIENTE CON RELACI??N AL SERVICIO DE DIFUSI??N POR SUSCRIPCI??N: El cliente se obliga especialmente. A) a no manipular la fibra ??ptica en ning??n otro equipo ya que su ruptura ocasionara el corte de la se??al y sub distribuir el servicio a terceras personas B) No conectar equipos adicionales a los consignados en este contrato. C) No alterar, remover ni cambiar total o parcialmente el equipo o los elementos entregados para la prestaci??n de este servicio. D) No contratar ni permitir que personas no autorizadas por TECNNITEL, realicen labores de reparaci??n en los equipos. E), EL cliente autoriza a TECNNITEL el sitio a Instalar los equipos y componentes necesarios para la prestaci??n del servicio, 2. CARGOS ESPECIALES Y TARIFAS EN CASO DE MORA: el cliente acepta que en caso de mora que exceda los diez d??as por falta de pago TECNNITEL suspender?? el servicio; la reconexi??n se har?? efectiva una vez el cliente cancele la deuda en su totalidad m??s la cancelaci??n de tres d??lares Americanos en concepto de cargo por rehabilitaci??n de servicio. 3. CARGOS Y TARIFAS EN CASO DE TRASLADO DEL DOMICILIO DEL SERVICIO DE DIFUSI??N POR SUSCRIPCI??N: En caso de traslado de domicilio el cliente deber?? notificar inmediatamente a TECNNITEL para programar la reconexi??n del servicio en el nuevo domicilio, entendiendo que el nuevo domicilio deber?? estar dentro de la red de cobertura del servicio de TECNNITEL. Un cargo de quince d??lares deber?? ser cancelado por el cliente correspondiente a reconexi??n por traslado de domicilio, valor que se har?? por anticipado. LIMITACIONES Y RESTRICCIONES DE MATERIAL PARA PROVEER DEL SERVICIO DE DIFUSI??N POR SUSCRIPCI??N.
        PAGO DE CUOTA: El cliente se compromete a pagar la cuota mensual y puntual ??nicamente en la oficina de TECNNITEL seg??n la fecha de contrataci??n.'));
        // Logo

        $fecha_instalacion = $contrato_internet[0]->fecha_instalacion;
        if($fecha_instalacion!=""){
            $corte_fecha = explode("-", $fecha_instalacion);

            $corte_dia = explode(" ", $corte_fecha[2]);
        }else{
            $corte_dia=["","",""];
            $corte_fecha=["","",""];
        }
        $fpdf->Image('assets/images/LOGO.png',15,83,60,25); //(x,y,w,h)
        $fpdf->SetXY(120,86);
        $fpdf->SetFont('Arial','B',18);
        $fpdf->cell(40,10,utf8_decode('PAGAR?? SIN PROTESTO'));

        $fpdf->SetFont('Arial','',12);
        $fpdf->SetXY(143,92);
        $fpdf->cell(40,10,utf8_decode('Apopa '.$corte_dia[0].' de '.$this->spanishMes($corte_fecha[1]).' de '.$corte_fecha[0].'.'));

        $fpdf->SetXY(168,98);
        $fpdf->cell(40,10,utf8_decode('Por: U$ '.($contrato_internet[0]->cuota_mensual*$contrato_internet[0]->periodo)));

        $fpdf->SetFont('Arial','',10);
        $fpdf->SetXY(15,106);
        $fpdf->MultiCell(186,5,utf8_decode('POR ESTE PAGAR??, YO, '.$cliente->nombre.', me obligo a pagar incondicionalmente a TECNNITEL, la cantidad de '.($contrato_internet[0]->cuota_mensual*$contrato_internet[0]->periodo).' U$ D??lares, reconociendo, en caso de mora, el inter??s del (DIEZ%) 10 por ciento mensual sobre saldo Insoluto. 
La suma antes mencionada la pagar?? en esta ciudad, en las oficinas principales de TECNNITEL, el d??a '.$corte_dia[0].' de '.$this->spanishMes($corte_fecha[1]).' del a??o '.$corte_fecha[0]).'.');

        $fpdf->SetXY(15,132);
        $fpdf->MultiCell(186,5,utf8_decode('En caso de acci??n jur??dica y de ejecuci??n, se??alo la ciudad de apopa como domicilio especial, siendo a mi cargo, cualquier gasto que la sociedad acreedora antes mencionada hiciere en el cobro de la presente obligaci??n, inclusive los llamados personales y facult?? a la sociedad para que designe al depositario judicial de los bienes que se me embarguen a quien revelo de la obligaci??n.'));
        $fpdf->SetXY(50,150);
        $fpdf->SetFont('Arial','B',11);
        $fpdf->cell(40,10,utf8_decode('DUI: '.$cliente->dui).'                       NIT: '.$cliente->nit);
        $fpdf->SetXY(110,158);
        $fpdf->SetFont('Arial','',11);
        $fpdf->cell(40,10,utf8_decode('FIRMA DEL CLIENTE: ______________________'));

        $fpdf->SetFont('Arial','B',11);
        $fpdf->SetXY(26,166);
        $fpdf->cell(40,10,utf8_decode('TERMINOS Y CONTRATACIONES GENERALES DE CONTRATACI??N DE TECNNITEL'));
        $fpdf->SetXY(15,174);
        $fpdf->SetFont('Arial','',10);
        $fpdf->MultiCell(186,5,utf8_decode('Los terminos y condiciones indicados en el mismo por parte de TECNNITEL de Nacionalidad Salvadore??a de este domicilio, en adelante denominada "EI PROVEEDOR". Las condiciones particulares en cuanto a plazo, tarifas y especificaciones de equipo para la prestaci??n de servicios a cada CLIENTE, se encuentran todas detalladas en el presente CONTRATO DE SERVICIO que El CLIENTE suscribe con EI PROVEEDOR, los cuales forman parte Integrante del presente documento CONDICIONES GENERAL APLICABLES 1. PLAZO; el plazo obligatorio de vigencia aplicable a la prestaci??n de los servicios del proveedor que entrar?? en vigencia se estipula en el presente contrato que El CLIENTE suscribe con EL PROVEEDOR y contar?? a partir de la fecha de suscripci??n. Una vez transcurrido el plazo obligatorio antes indicado, el plazo de contrato de cada servicio continuar?? por tiempo indefinido TERMINACION: anticipada; en caso de que EL CLIENTE solicite la terminaci??n dentro del plazo obligatorio ant Indicado, deber?? pagar a El PROVEEDOR, todos y cada unos de los cargos pendientes del pago a la fecha de terminaci??n efectiva del servicio de que se traten y adem??s le obliga a pagar en concepto de penalidad por terminaci??n anticipadas las cantidades se??aladas en El CONTRATO DE SERVICIO que corresponda. B) Suspensi??n por mora EL PROVEEDOR podr?? suspender cualquiera de los servicios contratados por Incumplimientos de las obligaciones EI CLIENTE este podr?? dar por terminado el plazo de vigencia del presente CONTRATO DE SERVICIO que corresponda.'));
        $fpdf->SetXY(38,249);
        //$fpdf->setFillColor(0,0,0); 
        //$fpdf->SetTextColor(255,255,255);
        $fpdf->SetFont('Arial','B',11);
        $fpdf->MultiCell(135,5,utf8_decode('Direcci??n: Centro comercial Pericentro Local 22 Apopa, San Salvador
        Correo Electronico: atencion@uninet.com.sv'),1,'C',0);
        $fpdf->SetTextColor(0,0,0);

        

        


        
        $fpdf->Output();
        exit;

    }

    private function spanishMes($m){
        $x=1;
        $meses = array("Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre");
        foreach ($meses as $value) {
            if($m==$x){
                return $value;
            }
            $x++;
            
        }
    }


    private function correlativo($id,$digitos){
        //id correlativo 
        /*
            1 cof
            2 ccf 
            3 cliente
            4 tv 
            5 inter
            6 orden 
            7 traslado
            8 reconexion
            9 suspension
        */

        $correlativo = Correlativo::find($id);
        $ultimo = $correlativo->ultimo+1;

        return $this->get_correlativo($ultimo,$digitos);

    }

    private function setCorrelativo($id){

        //id correlativo 
        /*
            1 cof
            2 ccf 
            3 cliente
            4 tv 
            5 inter
            6 orden 
            7 traslado
            8 reconexion
            9 suspension
        */
        $correlativo = Correlativo::find($id);
        $ultimo = $correlativo->ultimo+1;
        Correlativo::where('id',$id)->update(['ultimo' =>$ultimo]);
    }

    private function get_correlativo($ult_doc,$long_num_fact){
        $ult_doc=trim($ult_doc);
        $len_ult_valor=strlen($ult_doc);
        $long_increment=$long_num_fact-$len_ult_valor;
        $valor_txt="";
        if ($len_ult_valor<$long_num_fact) {
            for ($j=0;$j<$long_increment;$j++) {
            $valor_txt.="0";
            }
        } else {
            $valor_txt="";
        }
        $valor_txt=$valor_txt.$ult_doc;
        return $valor_txt;
    }

    //Funciones de Ordenes cliente 
    public function ordenes_index($id){
        $ordenes = Ordenes::where('id_cliente',$id)->get();
        $id_cliente =$id;
        $cliente = Cliente::find($id);
        $nombre_cliente = $cliente->nombre;
        return view('ordenes.index',compact('ordenes','id_cliente','nombre_cliente'));
    }

    public function ordenes_create($id){
        $obj_actividades = Actividades::all(); 
        $obj_tecnicos = Tecnicos::all();
        $id_cliente = $id;
        $cliente = Cliente::find($id);
        $cod_cliente = $cliente->codigo;
        $nombre_cliente = $cliente->nombre;
        return view('ordenes.create', compact('obj_actividades','obj_tecnicos','id_cliente','cod_cliente','nombre_cliente'));
    }

    public function ordenes_edit($id,$id_cliente){
        $orden = Ordenes::find($id);
        $obj_actividades = Actividades::all();
        $obj_tecnicos = Tecnicos::all();
        
        return view("ordenes.edit",compact('orden','obj_actividades','obj_tecnicos','id_cliente'));
    }


    //funciones para suspenciones cliente 

    public function suspensiones_index($id){
        $suspensiones = Suspensiones::where('id_cliente',$id)->get();
        $id_cliente =$id;
        $cliente = Cliente::find($id);
        $nombre_cliente = $cliente->nombre;
        return view('suspensiones.index',compact('suspensiones','id_cliente','nombre_cliente'));
    }

    public function suspensiones_create($id){
        $obj_tecnicos = Tecnicos::all();
        $id_cliente=$id;
        $cliente = Cliente::find($id);
        $cod_cliente = $cliente->codigo;
        $nombre_cliente = $cliente->nombre;
        return view('suspensiones.create', compact('obj_tecnicos','id_cliente','cod_cliente','nombre_cliente'));
    }

    public function suspensiones_edit($id,$id_cliente){
        $suspension = Suspensiones::find($id);
        $obj_tecnicos = Tecnicos::all();
        return view("suspensiones.edit",compact('suspension','obj_tecnicos','id_cliente'));
        
        
    }


    //funciones para reconexiones cliente 

    public function reconexiones_index($id){
        $reconexiones = Reconexion::where('id_cliente',$id)->get();
        $id_cliente =$id;
        $cliente = Cliente::find($id);
        $nombre_cliente = $cliente->nombre;
      
        return view('reconexiones.index',compact('reconexiones','id_cliente','nombre_cliente'));
    }

    public function reconexiones_create($id){
        $obj_tecnicos = Tecnicos::all();
        $id_cliente=$id;
        $cliente = Cliente::find($id);
        $cod_cliente = $cliente->codigo;
        $nombre_cliente = $cliente->nombre;
       
        return view('reconexiones.create', compact('obj_tecnicos','id_cliente','cod_cliente','nombre_cliente'));
    }

    public function reconexiones_edit($id,$id_cliente){
        $reconexion = Reconexion::find($id);
        $obj_tecnicos = Tecnicos::all();
        return view("reconexiones.edit",compact('reconexion','obj_tecnicos','id_cliente'));
        
        
    }

    //traslados para reconexiones cliente 

    public function traslados_index($id){
        $traslados = Traslados::where('id_cliente',$id)->get();
        $id_cliente =$id;
        $cliente = Cliente::find($id);
        $nombre_cliente = $cliente->nombre;
      
        return view('traslados.index',compact('traslados','id_cliente','nombre_cliente'));
    }

    public function traslados_create($id){
        $obj_tecnicos = Tecnicos::all();
        $obj_departamentos = Departamentos::all();
        $id_cliente=$id;
        $cliente = Cliente::find($id);
        $cod_cliente = $cliente->codigo;
        $nombre_cliente = $cliente->nombre;
       
        return view('traslados.create', compact('obj_tecnicos','id_cliente','cod_cliente','nombre_cliente','obj_departamentos'));
      
    }

    public function traslados_edit($id,$id_cliente){

        $traslado = Traslados::find($id);
        $obj_tecnicos = Tecnicos::all();
        $obj_departamentos = Departamentos::all();
        return view("traslados.edit",compact('traslado','obj_tecnicos','obj_departamentos','id_cliente'));
        
        
    }


    public function index_contratos(){
        $id=0;
        $cliente = Cliente::find($id);
        $contrato_tv= Tv::select('id','id_cliente','numero_contrato','fecha_instalacion','contrato_vence','identificador','activo');

        $contratos= Internet::select('id','id_cliente','numero_contrato','fecha_instalacion','contrato_vence','identificador','activo')
                            ->unionAll($contrato_tv)
                            ->get();

        $inter_activos = Internet::where('activo',1)->get();
        $tv_activos = Tv::where('activo',1)->get();
        $estado=-1;
        $tipo_servicio="";

        return view('contratos.all_index',compact('contratos','cliente','id','inter_activos','tv_activos','estado','tipo_servicio'));
        

    }

    public function getContratos(Request $request){
        $columns = array( 
            0 =>'numero_contrato',
            1 =>'nombre',
            2 =>'fecha_inicio',
            3 => 'fecha_fin',
            4=> 'identificador',
            5=> 'activo',
            6=> 'id'
        );
        //$totalData = Cliente::where('activo',1)->count();
        $contrato_tv= Tv::select('tvs.id','clientes.nombre','tvs.id_cliente','tvs.numero_contrato','tvs.fecha_instalacion','tvs.contrato_vence','tvs.identificador','tvs.activo')
        ->join('clientes','tvs.id_cliente','=','clientes.id');

        $totalData= Internet::select('internets.id','clientes.nombre','internets.id_cliente','internets.numero_contrato','internets.fecha_instalacion','internets.contrato_vence','internets.identificador','internets.activo')
            ->join('clientes','internets.id_cliente','=','clientes.id')
            ->unionAll($contrato_tv)
            ->count();

        $totalFiltered = $totalData; 
        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        if(empty($request->input('search.value')))
        {            
            $posts = Internet::select('internets.id','clientes.nombre','internets.id_cliente','internets.numero_contrato','internets.fecha_instalacion','internets.contrato_vence','internets.identificador','internets.activo')
            ->join('clientes','internets.id_cliente','=','clientes.id')
            ->unionAll($contrato_tv)
            ->offset($start)
            ->limit($limit)
            ->orderBy($order,$dir)
            ->get();
        }else {
            $search = $request->input('search.value'); 
            $posts =Internet::select('internets.id','clientes.nombre','internets.id_cliente','internets.numero_contrato','internets.fecha_instalacion','internets.contrato_vence','internets.identificador','internets.activo')
            ->join('clientes','internets.id_cliente','=','clientes.id')
            ->unionAll($contrato_tv)  
            ->where('numero_contrato','LIKE',"%{$search}%")
            ->orWhere('nombre', 'LIKE',"%{$search}%")
            ->offset($start)
            ->limit($limit)
            ->orderBy($order,$dir)
            ->get();

            $totalFiltered = Internet::select('internets.id','clientes.nombre','internets.id_cliente','internets.numero_contrato','internets.fecha_instalacion','internets.contrato_vence','internets.identificador','internets.activo')
            ->join('clientes','internets.id_cliente','=','clientes.id')
            ->unionAll($contrato_tv)
            ->where('numero_contrato','LIKE',"%{$search}%")
            ->orWhere('nombre', 'LIKE',"%{$search}%")
            ->count();
        }

        $data = array();
        if(!empty($posts))
        {
            foreach ($posts as $row)
            {
                //$show =  route('posts.show',$post->id);
                //$edit =  route('posts.edit',$post->id);

                $nestedData['numero_contrato'] = $row->numero_contrato;
                $nestedData['nombre'] = $row->nombre;
                //------------------------------------------------
                $fecha_inicio='';
                if (isset($row->fecha_instalacion)==1){
                    $fecha_inicio = $row->fecha_instalacion->format('d/m/Y');
                }
                $nestedData['fecha_inicio'] = $fecha_inicio;
                //-------------------------------------------------
                $fecha_fin='';
                if (isset($row->contrato_vence)==1){
                    $fecha_fin = $row->contrato_vence->format('d/m/Y');
                }
                $nestedData['fecha_fin'] = $fecha_fin;
                //--------------------------------------------------
                $identificador='';
                if($row->identificador==1){ $identificador = '<div class="col-md-9 badge badge-pill badge-primary">Internet </div>';}
                if($row->identificador==2){ $identificador = '<div class="col-md-9 badge badge-pill badge-light">Televisi??n </div>'; }
                $nestedData['identificador'] =$identificador;
                //---------------------------------------------------
                $activo='';
                if($row->activo==1){ $activo = '<div class="col-md-9 badge badge-pill badge-success">Activo</div>';}
                if($row->activo==0){ $activo = '<div class="col-md-9 badge badge-pill badge-secondary">Inactivo</div>'; }
                if($row->activo==2){ $activo = '<div class="col-md-9 badge badge-pill badge-danger">Suspendido</div>'; }
                if($row->activo==3){ $activo = '<div class="col-md-9 badge badge-pill badge-warning">Vencido</div>'; }
                $nestedData['activo'] =$activo;
                //----------------------------------------------------
                $fecha_actual = strtotime(date("d-m-Y H:i:00",time()));
                $fecha_entrada = strtotime($row->contrato_vence); $id=0;
                $cmstado ='';
                if($fecha_actual<$fecha_entrada && $id !=0 ){
                    $cmstado = '<a class="dropdown-item" href="'.url('contrato/activo/'.$row->id.'/'.$row->identificador).'" >Cambiar estado</a></div></div>';
                }
                $actionBtn = '<div class="btn-group mr-1 mt-2">
                <button type="button" class="btn btn-primary">Acciones</button>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="mdi mdi-chevron-down"></i>
                </button>
                <div class="dropdown-menu">
                <a class="dropdown-item" href="'.url('contrato/vista/'.$row->id.'/'.$row->identificador).'" target="_blank">Ver contrato</a>'.$cmstado;
                $nestedData['action']=$actionBtn;

                //$nestedData['created_at'] = date('j M Y h:i a',strtotime($post->created_at));
                //$nestedData['options'] = "&emsp;<a href='{$show}' title='SHOW' ><span class='glyphicon glyphicon-list'></span></a>
                //                    &emsp;<a href='{$edit}' title='EDIT' ><span class='glyphicon glyphicon-edit'></span></a>";
                $data[] = $nestedData;

            }
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),  
            "recordsTotal"    => intval($totalData),  
            "recordsFiltered" => intval($totalFiltered), 
            "data"            => $data   
        );
        echo json_encode($json_data); 
    }

    public function filtro_contratos(Request $request){
       
        if($request->tipo_servicio=="" && $request->estado==""){

            $contrato_tv= Tv::select('id','id_cliente','numero_contrato','fecha_instalacion','contrato_vence','identificador','activo');
    
            $contratos= Internet::select('id','id_cliente','numero_contrato','fecha_instalacion','contrato_vence','identificador','activo')
                                ->unionAll($contrato_tv)
                                ->get();
        }elseif($request->tipo_servicio=="Internet"){
            if($request->estado==""){

                $contratos= Internet::select('id','id_cliente','numero_contrato','fecha_instalacion','contrato_vence','identificador','activo')
                                    ->get();
               
            }else{
                $contratos= Internet::select('id','id_cliente','numero_contrato','fecha_instalacion','contrato_vence','identificador','activo')
                                    ->where('activo',$request->estado)
                                    ->get();
                
            }

        }elseif($request->tipo_servicio==""){

            if($request->estado==""){
                $contrato_tv= Tv::select('id','id_cliente','numero_contrato','fecha_instalacion','contrato_vence','identificador','activo');
                $contratos= Internet::select('id','id_cliente','numero_contrato','fecha_instalacion','contrato_vence','identificador','activo')
                                    ->unionAll($contrato_tv)
                                    ->get();
               
            }else{
                $contrato_tv= Tv::select('id','id_cliente','numero_contrato','fecha_instalacion','contrato_vence','identificador','activo')->where('activo',$request->estado);
                $contratos= Internet::select('id','id_cliente','numero_contrato','fecha_instalacion','contrato_vence','identificador','activo')
                                    ->unionAll($contrato_tv)
                                    ->where('activo',$request->estado)
                                    ->get();
                
            }

        }else{
            if($request->estado==""){

                $contratos= Tv::select('id','id_cliente','numero_contrato','fecha_instalacion','contrato_vence','identificador','activo')
                                    ->get();
                //echo "entre1";
            }else{
                $contratos= Tv::select('id','id_cliente','numero_contrato','fecha_instalacion','contrato_vence','identificador','activo')
                                    ->where('activo',$request->estado)
                                    ->get();
                //echo "entre2";
            }

        }
        

        $inter_activos = Internet::where('activo',1)->get();
        $tv_activos = Tv::where('activo',1)->get();
        $id=0;
        $estado = $request->estado;
        $tipo_servicio = $request->tipo_servicio;

        return view('contratos.index',compact('contratos','id','inter_activos','tv_activos','estado','tipo_servicio'));

    }

    public function gen_cobros(){
        $dia_actual = date('j');
        $fecha_actual = date('Y-m-d');
        $fecha_vence = strtotime ( '+10 day' , strtotime ( $fecha_actual ) ) ;
        $fecha_vence = date ( 'Y-m-d' , $fecha_vence );
        $mes_servicio = strtotime ( '-1 month' , strtotime ( $fecha_actual ) ) ;
        $mes_servicio = date ( 'Y-m-d' , $mes_servicio );
        $internet = Internet::where('dia_gene_fact',$dia_actual)->where('activo',1)->get();
        $tv = Tv::where('dia_gene_fact',$dia_actual)->where('activo',1)->get();

        $primer_fac_inter = new DateTime();
        $primer_fac_tv = new DateTime();
        

        $fecha_fa = new DateTime($fecha_actual);

        foreach ($internet as $value) {
            $primer_fac_inter = new DateTime($value->fecha_primer_fact);
            if($primer_fac_inter<=$fecha_fa){
                //comparar cantidad de cargo y abonos
                $cargos_inter = Abono::where('id_cliente',$value->id_cliente)->where('tipo_servicio',1)->where('cargo','!=','0.00')->get()->count();
                $abono_inter = Abono::where('id_cliente',$value->id_cliente)->where('tipo_servicio',1)->where('abono','!=','0.00')->where('pagado',1)->where('anulado',0)->where('anulado',0)->get()->count();
                $pagado=0;
                $chek_mes_servicio=0;
                if($abono_inter>$cargos_inter){
                    $pagado=1;
                }else{

                    $chek_mes_servicio = Abono::where('id_cliente',$value->id_cliente)->where('tipo_servicio',1)->where('cargo','!=','0.00')->where('mes_servicio',$mes_servicio)->get()->count();
                }

                if($chek_mes_servicio == 0 ){

                    $abono = new Abono();
                    $abono->id_cliente = $value->id_cliente;
                    $abono->tipo_servicio = 1;
                    $abono->mes_servicio = $mes_servicio;
                    $abono->fecha_aplicado = date('Y-m-d');
                    $abono->cargo = $value->cuota_mensual;
                    $abono->abono = 0.00;
                    $abono->fecha_vence = $fecha_vence;
                    $abono->anulado = 0;
                    $abono->pagado = $pagado;
                    $abono->save();
                    //echo "Cargo ".$cargos_inter." pago: ".$abono_inter;
                }
                
            }
        
        }
        foreach ($tv as $value) {
            $primer_fac_tv = new DateTime($value->fecha_primer_fact);
            if($primer_fac_tv<=$fecha_fa){

                //comparar cantidad de cargo y abonos
                $cargos_tv = Abono::where('id_cliente',$value->id_cliente)->where('tipo_servicio',2)->where('cargo','!=','0.00')->get()->count();
                $abono_tv = Abono::where('id_cliente',$value->id_cliente)->where('tipo_servicio',2)->where('abono','!=','0.00')->where('pagado',1)->where('anulado',0)->get()->count();
                $pagado=0;
                $chek_mes_servicio_tv=0;
                if($abono_tv>$cargos_tv){
                    $pagado=1;

                    $chek_mes_servicio_tv = Abono::where('id_cliente',$value->id_cliente)->where('tipo_servicio',2)->where('cargo','!=','0.00')->where('mes_servicio',$mes_servicio)->get()->count();
                }

                if($chek_mes_servicio_tv == 0 ){

                    $abono = new Abono();
                    $abono->id_cliente = $value->id_cliente;
                    $abono->tipo_servicio = 2;
                    $abono->mes_servicio = $mes_servicio;
                    $abono->cargo = $value->cuota_mensual;
                    $abono->fecha_aplicado = date('Y-m-d');
                    $abono->abono = 0.00;
                    $abono->fecha_vence = $fecha_vence;
                    $abono->anulado = 0;
                    $abono->pagado = $pagado;
                    $abono->save();
                }
            }
    
        }
    //return $dia_actual;
    flash()->success("Cobros generados exitosamente")->important();
    return back();

    }

    public function estado_cuenta($id){
        $abono_inter = Abono::where('id_cliente',$id)->where('tipo_servicio',1)->where('anulado',0)->get();
        $abono_tv = Abono::where('id_cliente',$id)->where('tipo_servicio',2)->where('anulado',0)->get();

        return view('estado_cuenta.index',compact('abono_inter','abono_tv','id'));

    }

    public function estado_cuenta_destroy($id){
        Abono::destroy($id);
        $obj_controller_bitacora=new BitacoraController();	
        $obj_controller_bitacora->create_mensaje('Estado de cuenta eliminado id: '.$id);
        
        flash()->success("Registro eliminado exitosamente!")->important();
        return back();
    }

    public function estado_cuenta_pdf($id,$tipo_servicio,$fecha_i,$fecha_f){

        $cliente = Cliente::find($id);
        $fecha_inicio = Carbon::createFromFormat('Y-m-d', $fecha_i);
        $fecha_fin = Carbon::createFromFormat('Y-m-d', $fecha_f);
        $estado_cuenta = Abono::select('recibo','tipo_servicio','id_cobrador','numero_documento','mes_servicio','fecha_aplicado','fecha_vence','cargo','abono','cesc_cargo','cesc_abono')
                                ->whereBetween('created_at',[$fecha_inicio,$fecha_fin])
                                ->where('tipo_servicio',$tipo_servicio)
                                ->where('id_cliente',$id)
                                ->where('anulado',0)
                                ->orderBy('mes_servicio','ASC')
                                ->get();
        $internet = Internet::where('id_cliente',$id)->where('activo',1)->get();
        $tv = Tv::where('id_cliente',$id)->where('activo',1)->get();
        $fpdf = new FpdfEstadoCuenta('L','mm', 'Letter');
        $fpdf->AliasNbPages();
        $fpdf->AddPage();
        $fpdf->SetTitle('ESTADO DE CUENTA | UNINET');

        $fpdf->SetXY(250,10);
        $fpdf->SetFont('Arial','',8);
     
        $fpdf->Cell(20,10,utf8_decode('P??GINAS: 000'.'{nb}'),0,1,'R');

        $fpdf->SetXY(250,15);
        $fpdf->SetFont('Arial','',8);
        $fpdf->Cell(20,10,utf8_decode('GENERADO POR: '.Auth::user()->name).' '.date('d/m/Y h:i:s a'),0,1,'R');

        $fpdf->SetXY(15,25);
        $fpdf->SetFont('Arial','B',9);
        $por_fecha_i = explode("-", $fecha_i);
        $por_fecha_f = explode("-", $fecha_f);
        $fpdf->Cell(20,10,utf8_decode('ESTADO DE CUENTA del '.$por_fecha_i[2].' de '.$this->spanishMes($por_fecha_i[1]).' de '.$por_fecha_i[0].'  al '.$por_fecha_f[2].' de '.$this->spanishMes($por_fecha_f[1]).' de '.$por_fecha_f[0]));

        $fpdf->SetXY(15,29);
        $fpdf->SetFont('Arial','B',9);
        $fpdf->Cell(20,10,utf8_decode('SUCURSAL DE '.Auth::user()->get_sucursal->nombre));

        $fpdf->SetXY(15,33);
        $fpdf->SetFont('Arial','',9);
        $fpdf->Cell(20,10,utf8_decode('CLIENTE: '.$cliente->codigo.' '.$cliente->nombre));

        $fpdf->SetXY(15,40);
        $fpdf->MultiCell(200,5,utf8_decode('DIRECCI??N: '.$cliente->dirreccion_cobro),0,'L');
        if($cliente->internet==1){
            $inter = "ACTIVO";
        }else{
            $inter = "INACTIVO";
        }
        if($cliente->tv==1){
            $tv_tex = "ACTIVO";
        }else{
            $tv_tex = "INACTIVO";
        }
        $fpdf->SetXY(250,33);
        if($tipo_servicio==1){
            $fpdf->Cell(20,10,utf8_decode('Dia de cobro: '.$internet[0]->dia_gene_fact),0,1,'R');

        }else{
            $fpdf->Cell(20,10,utf8_decode('Dia de cobro: '.$tv[0]->dia_gene_fact),0,1,'R');
        }
        $fpdf->SetXY(250,37);
        $fpdf->Cell(20,10,utf8_decode('INTERNET: '.$inter),0,1,'R');
        $fpdf->SetXY(250,41);
        $fpdf->Cell(20,10,utf8_decode('TV: '.$tv_tex),0,1,'R');

        $header=array('N resivo','Codigo de cobrador','Tipo servicio','N comprobante','Mes de servicio',utf8_decode('Aplicaci??n'),'Vencimiento','Cargo','Abono', 'Impuesto','Total');
        
        $fpdf->BasicTable($header,$estado_cuenta);



        $fpdf->Output();
        exit;

    }
    
}

