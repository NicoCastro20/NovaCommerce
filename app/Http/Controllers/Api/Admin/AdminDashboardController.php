<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    /**
     * Estadísticas agregadas del panel de administración.
     * Se calculan siempre en tiempo real para que el botón "Actualizar"
     * del panel devuelva datos frescos.
     */
    public function show(): JsonResponse
    {
        $datos = (function (): array {
            $hace30 = Carbon::now()->subDays(29)->startOfDay();

            // ── Conteos básicos ────────────────────────────────────────────
            $totalUsuarios  = User::count();
            $totalProductos = Product::where('is_active', true)->count();
            $totalPedidos   = Order::count();

            // Ingresos: solo pedidos no cancelados ni devueltos.
            $totalIngresos = (float) Order::query()
                ->whereNotIn('status', ['cancelado', 'devuelto'])
                ->sum('total');

            // ── Devoluciones ───────────────────────────────────────────────
            $totalDevoluciones = ReturnRequest::count();

            // ── Pedidos por estado (todos los enums, aunque tengan 0) ──────
            $estados = ['pendiente', 'pagado', 'enviado', 'entregado', 'cancelado', 'devuelto'];

            $conteoEstados = Order::query()
                ->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();

            $pedidosPorEstado = [];
            foreach ($estados as $estado) {
                $pedidosPorEstado[$estado] = (int) ($conteoEstados[$estado] ?? 0);
            }

            // ── Últimos 10 pedidos ─────────────────────────────────────────
            $pedidosRecientes = Order::query()
                ->with('comprador:id,name,email')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(fn (Order $pedido) => [
                    'id'           => $pedido->id,
                    'order_number' => $pedido->order_number,
                    'status'       => $pedido->status,
                    'total'        => (float) $pedido->total,
                    'created_at'   => $pedido->created_at,
                    'buyer'        => $pedido->comprador !== null ? [
                        'id'    => $pedido->comprador->id,
                        'name'  => $pedido->comprador->name,
                        'email' => $pedido->comprador->email,
                    ] : null,
                ]);

            // ── Top 5 productos más vendidos ───────────────────────────────
            // Se excluyen los items de pedidos cancelados o devueltos para que
            // el ranking refleje ventas reales.
            $topProductos = OrderItem::query()
                ->select(
                    'product_id',
                    DB::raw('SUM(quantity) as total_units'),
                    DB::raw('SUM(subtotal) as total_revenue'),
                )
                ->whereHas('pedido', function ($q): void {
                    $q->whereNotIn('status', ['cancelado', 'devuelto']);
                })
                ->groupBy('product_id')
                ->orderByDesc('total_units')
                ->limit(5)
                ->with('producto:id,name,slug,price')
                ->get()
                ->map(fn (OrderItem $item) => [
                    'product_id'    => $item->product_id,
                    'name'          => $item->producto?->name,
                    'slug'          => $item->producto?->slug,
                    'price'         => $item->producto !== null ? (float) $item->producto->price : null,
                    'total_units'   => (int) $item->total_units,
                    'total_revenue' => (float) $item->total_revenue,
                ]);

            // ── Ventas últimos 30 días, agrupadas por día ──────────────────
            $ventasPorDia = Order::query()
                ->whereNotIn('status', ['cancelado', 'devuelto'])
                ->where('created_at', '>=', $hace30)
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('SUM(total) as total'),
                    DB::raw('COUNT(*) as count'),
                )
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(fn ($fila) => [
                    'date'  => (string) $fila->date,
                    'total' => (float) $fila->total,
                    'count' => (int) $fila->count,
                ]);

            // ── Nuevos usuarios en los últimos 30 días ─────────────────────
            $nuevosUsuarios = User::where('created_at', '>=', $hace30)->count();

            // ── Suscriptores Premium activos ───────────────────────────────
            // Incluye a quienes cancelaron pero aún disfrutan del beneficio
            // hasta premium_until (alineado con User::esPremium()).
            $suscriptoresPremium = User::query()
                ->where('premium_until', '>', now())
                ->count();

            return [
                'total_users'             => $totalUsuarios,
                'total_products'          => $totalProductos,
                'total_orders'            => $totalPedidos,
                'total_revenue'           => round($totalIngresos, 2),
                'total_returns'           => $totalDevoluciones,
                'premium_subscribers'     => $suscriptoresPremium,
                'orders_by_status'        => $pedidosPorEstado,
                'recent_orders'           => $pedidosRecientes,
                'top_products'            => $topProductos,
                'sales_last_30_days'      => $ventasPorDia,
                'new_users_last_30_days'  => $nuevosUsuarios,
                'generated_at'            => Carbon::now()->toIso8601String(),
            ];
        })();

        return response()->json([
            'success' => true,
            'message' => 'Estadísticas obtenidas correctamente.',
            'data'    => $datos,
        ]);
    }
}
