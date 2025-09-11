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

            // Отправляем подтверждение клиенту
            $this->sendClientConfirmation($contactRequest);

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

    public function orderService(Request $request, Service $service)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:100',
            'message' => 'nullable|string|max:1000',
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
            $contactRequest = ContactRequest::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'company' => $request->company,
                'subject' => "Заказ услуги: {$service->title}",
                'message' => $request->message ?: "Заинтересован в услуге: {$service->title}",
                'service' => $service->title,
                'source' => ContactRequest::SOURCE_SERVICE_PAGE,
                'meta_data' => [
                    'service_id' => $service->id,
                    'service_slug' => $service->slug,
                    'service_price' => $service->price_from,
                    'user_agent' => $request->userAgent(),
                    'ip_address' => $request->ip(),
                    'submitted_at' => now()->toISOString()
                ]
            ]);

            $this->sendAdminNotification($contactRequest);
            $this->sendClientConfirmation($contactRequest);

            Log::info('Заказ услуги с сайта', [
                'request_id' => $contactRequest->id,
                'service' => $service->title,
                'email' => $contactRequest->email
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Спасибо! Ваша заявка на услугу принята. Наш менеджер свяжется с вами для уточнения деталей.'
                ]);
            }

            return back()->with('success', 'Спасибо! Ваша заявка на услугу принята. Наш менеджер свяжется с вами для уточнения деталей.');

        } catch (\Exception $e) {
            Log::error('Ошибка при заказе услуги', [
                'service_id' => $service->id,
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
            // Здесь можно отправить email администратору
            // Mail::to(config('mail.admin_email'))->send(new AdminContactNotification($contactRequest));
            
            // Пока просто логируем
            Log::info('Уведомление администратору о новой заявке', [
                'id' => $contactRequest->id,
                'subject' => $contactRequest->subject
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка отправки уведомления администратору', [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function sendClientConfirmation(ContactRequest $contactRequest)
    {
        try {
            // Здесь можно отправить подтверждение клиенту
            // Mail::to($contactRequest->email)->send(new ClientConfirmation($contactRequest));
            
            Log::info('Подтверждение отправлено клиенту', [
                'email' => $contactRequest->email
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка отправки подтверждения клиенту', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
