<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\ContactRequest;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|min:2|max:100',
                'email' => 'required|email|max:255',
                'phone' => 'nullable|string|max:20',
                'company' => 'nullable|string|max:100',
                'subject' => 'required|string|max:200',
                'message' => 'required|string|min:10|max:2000',
                'service' => 'nullable|string|max:200',
                'source' => 'nullable|string|in:website,service_page,contact_form',
            ]);

            if ($validator->fails()) {
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'errors' => $validator->errors()
                    ], 422);
                }

                return back()
                    ->withErrors($validator)
                    ->withInput()
                    ->with('error', 'Пожалуйста, исправьте ошибки в форме.');
            }

            // Создаем заявку
            $contactRequest = ContactRequest::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'company' => $request->company,
                'subject' => $request->subject ?: 'Заказ услуги',
                'message' => $request->message,
                'service' => $request->service,
                'source' => $request->source ?: ContactRequest::SOURCE_WEBSITE,
                'meta_data' => [
                    'user_agent' => $request->userAgent(),
                    'ip_address' => $request->ip(),
                    'referer' => $request->header('referer'),
                    'submitted_at' => now()->toISOString()
                ]
            ]);

            // Отправляем уведомление администратору
            $this->sendAdminNotification($contactRequest);

            // Подтверждение клиенту не отправляем - только логируем
            Log::info('Заявка обработана без отправки подтверждения клиенту', [
                'id' => $contactRequest->id,
                'email' => $contactRequest->email
            ]);

            Log::info('Новая заявка с сайта', [
                'id' => $contactRequest->id,
                'email' => $contactRequest->email,
                'service' => $contactRequest->service
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Спасибо! Ваша заявка принята. Мы свяжемся с вами в ближайшее время.'
                ]);
            }

            return back()->with('success', 'Спасибо! Ваша заявка принята. Мы свяжемся с вами в ближайшее время.');

        } catch (\Exception $e) {
            Log::error('Ошибка при отправке заявки', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Произошла ошибка. Попробуйте еще раз или свяжитесь с нами по телефону.'
                ], 500);
            }

            return back()
                ->withInput()
                ->with('error', 'Произошла ошибка. Попробуйте еще раз или свяжитесь с нами по телефону.');
        }
    }

    public function orderService(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:100',
            'message' => 'nullable|string|max:1000',
            'service' => 'required|string|max:200',
            'product_id' => ['nullable','integer',\Illuminate\Validation\Rule::exists('products','id')->where('is_active',true)],
            'quantity' => 'required_with:product_id|nullable|integer|min:1|max:100000',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            return back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Пожалуйста, исправьте ошибки в форме.');
        }

        try {
            $product=$request->filled('product_id') ? \App\Models\Product::where('is_active',true)->findOrFail($request->integer('product_id')) : null;
            $service=$product ? mb_substr('Запрос поставки: '.$product->sku.' — '.$product->name,0,200) : $request->service;
            $contactRequest = ContactRequest::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'company' => $request->company,
                'subject' => $product ? mb_substr('Запрос поставки · '.$product->sku,0,200) : "Заказ продукта: {$request->service}",
                'message' => $product ? 'Товар № '.$product->id.': '.$product->name."\nКоличество: ".$request->integer('quantity')." шт.\n".($request->message ?? '') : ($request->message ?: "Заинтересован в продукте: {$request->service}"),
                'service' => $service,
                'source' => ContactRequest::SOURCE_SERVICE_PAGE,
                'meta_data' => [
                    ...($product ? ['product_id'=>$product->id,'quantity'=>$request->integer('quantity'),'product_name'=>$product->name,'sku'=>$product->sku,'request_type'=>'supply'] : []),
                    'user_agent' => $request->userAgent(),
                    'ip_address' => $request->ip(),
                    'referer' => $request->header('referer'),
                    'submitted_at' => now()->toISOString()
                ]
            ]);

            $this->sendAdminNotification($contactRequest);
            // Подтверждение клиенту не отправляем

            Log::info('Заказ продукта с сайта', [
                'request_id' => $contactRequest->id,
                'service' => $request->service,
                'email' => $contactRequest->email
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Спасибо! Ваша заявка на продукт принята. Наш менеджер свяжется с вами для уточнения деталей.'
                ]);
            }

            return back()->with('success', 'Спасибо! Ваша заявка на продукт принята. Наш менеджер свяжется с вами для уточнения деталей.');

        } catch (\Exception $e) {
            Log::error('Ошибка при заказе продукта', [
                'service' => $request->service,
                'error' => $e->getMessage()
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Произошла ошибка. Попробуйте еще раз или свяжитесь с нами по телефону.'
                ], 500);
            }

            return back()
                ->withInput()
                ->with('error', 'Произошла ошибка. Попробуйте еще раз или свяжитесь с нами по телефону.');
        }
    }

    private function sendAdminNotification(ContactRequest $contactRequest)
    {
        try {
            // Отправляем email администратору
            Mail::to(config('mail.admin_email', 'office@rualtech.ru'))
                ->send(new \App\Mail\AdminContactNotification($contactRequest));
            
            Log::info('Уведомление администратору отправлено', [
                'id' => $contactRequest->id,
                'subject' => $contactRequest->subject,
                'admin_email' => config('mail.admin_email', 'office@rualtech.ru')
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка отправки уведомления администратору', [
                'error' => $e->getMessage(),
                'contact_request_id' => $contactRequest->id
            ]);
        }
    }
}
