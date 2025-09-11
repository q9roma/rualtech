<!-- General Contact Form -->
<div x-data="{ 
    showContactForm: false,
    isSubmitting: false,
    formData: {
        name: '',
        email: '',
        phone: '',
        company: '',
        subject: 'Обратная связь',
        message: ''
    },
    errors: {},
    
    openContactForm() {
        this.showContactForm = true;
        this.resetForm();
    },
    
    closeContactForm() {
        this.showContactForm = false;
        this.resetForm();
    },
    
    resetForm() {
        this.formData = {
            name: '',
            email: '',
            phone: '',
            company: '',
            subject: 'Обратная связь',
            message: ''
        };
        this.errors = {};
        this.isSubmitting = false;
    },
    
    async submitContact() {
        if (this.isSubmitting) return;
        
        this.isSubmitting = true;
        this.errors = {};
        
        try {
            const response = await fetch('/contact', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    ...this.formData,
                    source: 'contact_form'
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccessMessage(data.message);
                this.closeContactForm();
            } else {
                if (data.errors) {
                    this.errors = data.errors;
                } else {
                    this.showErrorMessage(data.message || 'Произошла ошибка при отправке сообщения');
                }
            }
        } catch (error) {
            console.error('Ошибка:', error);
            this.showErrorMessage('Произошла ошибка. Попробуйте еще раз.');
        } finally {
            this.isSubmitting = false;
        }
    },
    
    showSuccessMessage(message) {
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 z-50 bg-green-500 text-white px-6 py-4 rounded-lg shadow-lg transform transition-all duration-300';
        notification.innerHTML = `
            <div class='flex items-center'>
                <svg class='w-5 h-5 mr-3' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                    <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5 13l4 4L19 7'></path>
                </svg>
                <span>${message}</span>
            </div>
        `;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 5000);
    },
    
    showErrorMessage(message) {
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 z-50 bg-red-500 text-white px-6 py-4 rounded-lg shadow-lg transform transition-all duration-300';
        notification.innerHTML = `
            <div class='flex items-center'>
                <svg class='w-5 h-5 mr-3' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                    <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 18L18 6M6 6l12 12'></path>
                </svg>
                <span>${message}</span>
            </div>
        `;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }
}" 
x-on:open-contact-form.window="openContactForm()"
x-on:keydown.escape.window="showContactForm && closeContactForm()">

    <!-- Contact Button (можно разместить где угодно) -->
    <button x-on:click="openContactForm()" 
            type="button"
            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
        </svg>
        Связаться с нами
    </button>

    <!-- Modal Overlay -->
    <div x-show="showContactForm" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-black bg-opacity-50"
         x-cloak>
        
        <div class="flex min-h-screen items-center justify-center px-4 py-6">
            <!-- Modal Content -->
            <div x-show="showContactForm"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 transform scale-95"
                 x-transition:enter-end="opacity-100 transform scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 transform scale-100"
                 x-transition:leave-end="opacity-0 transform scale-95"
                 class="w-full max-w-lg bg-white rounded-xl shadow-2xl"
                 x-on:click.stop>
                
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">
                            Связаться с нами
                        </h3>
                        <button x-on:click="closeContactForm()" 
                                type="button" 
                                class="text-gray-400 hover:text-gray-500 focus:outline-none">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <p class="mt-1 text-sm text-gray-600">
                        Заполните форму и мы свяжемся с вами в ближайшее время
                    </p>
                </div>
                
                <!-- Modal Body -->
                <form x-on:submit.prevent="submitContact()" class="px-6 py-4 space-y-4">
                    <!-- Name Field -->
                    <div>
                        <label for="contact_name" class="block text-sm font-medium text-gray-700 mb-1">
                            Имя <span class="text-red-500">*</span>
                        </label>
                        <input x-model="formData.name"
                               type="text" 
                               id="contact_name"
                               name="name"
                               required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                               :class="{ 'border-red-500': errors.name }"
                               placeholder="Ваше имя">
                        <div x-show="errors.name" class="mt-1 text-sm text-red-600" x-text="errors.name?.[0]"></div>
                    </div>
                    
                    <!-- Email Field -->
                    <div>
                        <label for="contact_email" class="block text-sm font-medium text-gray-700 mb-1">
                            Email <span class="text-red-500">*</span>
                        </label>
                        <input x-model="formData.email"
                               type="email" 
                               id="contact_email"
                               name="email"
                               required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                               :class="{ 'border-red-500': errors.email }"
                               placeholder="your@email.com">
                        <div x-show="errors.email" class="mt-1 text-sm text-red-600" x-text="errors.email?.[0]"></div>
                    </div>
                    
                    <!-- Phone Field -->
                    <div>
                        <label for="contact_phone" class="block text-sm font-medium text-gray-700 mb-1">
                            Телефон
                        </label>
                        <input x-model="formData.phone"
                               type="tel" 
                               id="contact_phone"
                               name="phone"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                               :class="{ 'border-red-500': errors.phone }"
                               placeholder="+7 (000) 000-00-00">
                        <div x-show="errors.phone" class="mt-1 text-sm text-red-600" x-text="errors.phone?.[0]"></div>
                    </div>
                    
                    <!-- Company Field -->
                    <div>
                        <label for="contact_company" class="block text-sm font-medium text-gray-700 mb-1">
                            Компания
                        </label>
                        <input x-model="formData.company"
                               type="text" 
                               id="contact_company"
                               name="company"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                               :class="{ 'border-red-500': errors.company }"
                               placeholder="Название компании">
                        <div x-show="errors.company" class="mt-1 text-sm text-red-600" x-text="errors.company?.[0]"></div>
                    </div>
                    
                    <!-- Subject Field -->
                    <div>
                        <label for="contact_subject" class="block text-sm font-medium text-gray-700 mb-1">
                            Тема <span class="text-red-500">*</span>
                        </label>
                        <input x-model="formData.subject"
                               type="text" 
                               id="contact_subject"
                               name="subject"
                               required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                               :class="{ 'border-red-500': errors.subject }"
                               placeholder="Тема обращения">
                        <div x-show="errors.subject" class="mt-1 text-sm text-red-600" x-text="errors.subject?.[0]"></div>
                    </div>
                    
                    <!-- Message Field -->
                    <div>
                        <label for="contact_message" class="block text-sm font-medium text-gray-700 mb-1">
                            Сообщение <span class="text-red-500">*</span>
                        </label>
                        <textarea x-model="formData.message"
                                  id="contact_message"
                                  name="message"
                                  rows="4"
                                  required
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                  :class="{ 'border-red-500': errors.message }"
                                  placeholder="Расскажите подробнее о вашем запросе..."></textarea>
                        <div x-show="errors.message" class="mt-1 text-sm text-red-600" x-text="errors.message?.[0]"></div>
                    </div>
                    
                    <!-- Modal Footer -->
                    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
                        <button x-on:click="closeContactForm()" 
                                type="button" 
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Отмена
                        </button>
                        <button type="submit" 
                                :disabled="isSubmitting"
                                class="px-6 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed flex items-center">
                            <svg x-show="isSubmitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span x-text="isSubmitting ? 'Отправка...' : 'Отправить сообщение'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
