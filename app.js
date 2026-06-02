"use strict";

const log = console.log.bind(console);

document.addEventListener("DOMContentLoaded", function(){

    document.getElementById('contactForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        const formData = {
            name: document.getElementById('name').value,
            email: document.getElementById('email').value,
            phone: document.getElementById('phone').value
        };

        const messageArea = document.getElementById('messageArea');

        try {
            const response = await fetch('submit.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            // Проброс ошибки на сервере
            if(result.error_details) log(result.error_details);

            if (result.success) {
                // Скрыть форму и показать сообщение об успехе
                document.getElementById('contactForm').innerHTML = '';
                messageArea.innerHTML = '<div class="success">' + result.message + '</div>';
            } else {
                // Показать ошибку над формой
                messageArea.innerHTML = '<div class="error">' + result.message + '</div>';
                // Скрыть через 5 сек
                setTimeout(() => {
                    messageArea.innerHTML = '';
                }, 5000);
            }
        } catch (error) {
            messageArea.innerHTML = '<div class="error">Ошибка соединения с сервером</div>';
            log(error);
        }
    });

});