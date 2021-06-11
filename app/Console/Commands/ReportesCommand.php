<?php

namespace App\Console\Commands;

use App\Articulo;
use App\DetalleVenta;
use App\Venta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ReportesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reportes:s3';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando para guardar los reportes en amazon';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->listarPdf();
        $disk = "s3";
        $ventas = venta::all();

        foreach ($ventas as $venta) {
            $ruta = "fundacion/ventas/pdf/venta_{$venta->id}.pdf";
            if (!Storage::disk($disk)->exists($ruta)) {
                $this->ventasPDF($venta->id);
            }
        }
    }


    public function listarPdf()
    {
        $articulos = Articulo::join('categorias', 'articulos.idcategoria', '=', 'categorias.id')
            ->select('articulos.id', 'articulos.idcategoria', 'articulos.codigo', 'articulos.nombre', 'categorias.nombre as nombre_categoria', 'articulos.precio_venta', 'articulos.stock', 'articulos.descripcion', 'articulos.condicion')
            ->orderBy('articulos.nombre', 'desc')->get();

        $cont = Articulo::count();

        $pdf = \PDF::loadView('pdf.articulospdf', ['articulos' => $articulos, 'cont' => $cont]);
        $fecha = now()->format('Y-m-d his a');
        Storage::disk('s3')->put("fundacion/articulos/pdf/invoice_$fecha.pdf", $pdf->output());
    }

    public function ventasPDF($id)
    {
        $venta = Venta::join('personas', 'ventas.idcliente', '=', 'personas.id')
            ->join('users', 'ventas.idusuario', '=', 'users.id')
            ->select(
                'ventas.id',
                'ventas.tipo_comprobante',
                'ventas.serie_comprobante',
                'ventas.num_comprobante',
                'ventas.created_at',
                'ventas.impuesto',
                'ventas.total',
                'ventas.estado',
                'personas.nombre',
                'personas.tipo_documento',
                'personas.num_documento',
                'personas.direccion',
                'personas.email',
                'personas.telefono',
                'users.usuario'
            )
            ->where('ventas.id', '=', $id)
            ->orderBy('ventas.id', 'desc')->take(1)->get();

        $detalles = DetalleVenta::join('articulos', 'detalle_ventas.idarticulo', '=', 'articulos.id')
            ->select(
                'detalle_ventas.cantidad',
                'detalle_ventas.precio',
                'detalle_ventas.descuento',
                'articulos.nombre as articulo'
            )
            ->where('detalle_ventas.idventa', '=', $id)
            ->orderBy('detalle_ventas.id', 'desc')->get();

        // $numventa = Venta::select('num_comprobante')->where('id', $id)->get();

        $pdf = \PDF::loadView('pdf.venta', ['venta' => $venta, 'detalles' => $detalles]);
        Storage::disk('s3')->put("fundacion/ventas/pdf/venta_{$id}.pdf", $pdf->output());

        // return $pdf->stream('venta-'.$numventa[0]->num_comprobante.'.pdf');

    }
}
