<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TransacaoController extends Controller
{
    public function realizarTransacao(Request $request, $id){
        $validator = Validator::make($request->all(), [
            "valor"=> "bail|required|integer",
            "tipo"=> "bail|required|string|in:c,d",
            "descricao"=> "bail|required|string|max:10"
        ]);

        if($validator->fails()){
            return (new Response)->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        
        if (!isset($id) || !is_numeric($id) || !is_int($id + 0) || $id < 0) {
            return (new Response)->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction();
        
        $cliente = DB::table("clientes")->select("id", "limite_conta", "saldo")->find($id);

        if(! isset($cliente) || is_null($cliente)) {
            DB::rollBack();
            return (new Response)->setStatusCode(Response::HTTP_NOT_FOUND);
        }

        $cliente->saldo = $request->tipo === "d" ? $cliente->saldo - $request->valor : $cliente->saldo + $request->valor;

        if($request->tipo === "d" && $cliente->saldo < -$cliente->limite_conta ) {
            DB::rollBack();
            return (new Response)->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            DB::table("transacoes")->insert([
                "cliente_id" => $cliente->id,
                "valor" => $request->valor,
                "tipo" => $request->tipo,
                "descricao" => $request->descricao
            ]);
        
            DB::table("clientes")->where("id", "=", $id)->update([
                "saldo" => $cliente->saldo
            ]);
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            return (new Response)->setStatusCode(Response::HTTP_LOCKED);
        }

        return response(["limite" => $cliente->limite_conta, "saldo" => $cliente->saldo], Response::HTTP_OK);
    }

    public function pegarExtrato($id){
        if (!isset($id) || !is_numeric($id) || !is_int($id + 0) || $id < 0) {
            return (new Response)->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    
        $cliente = DB::table("clientes")->select("limite_conta as limite", "saldo as total")->find($id);

        if(! isset($cliente) || is_null($cliente)) {
            return (new Response)->setStatusCode(Response::HTTP_NOT_FOUND);
        }

        $cliente->data_extrato = Carbon::now()->toIso8601ZuluString();  

        $transacoes = DB::table("transacoes")
        ->select("valor","tipo","descricao", "criado_em as realizado_em")
        ->where("cliente_id","=", $id)
        ->orderBy("criado_em","desc")
        ->limit(10)
        ->get()
        ->map(function($transacao){
            $transacao->realizado_em = Carbon::parse($transacao->realizado_em)->toIso8601ZuluString();
            return $transacao;
        });

        return response(["saldo" => $cliente, "ultimas_transacoes" => $transacoes], Response::HTTP_OK);    
    }
}
