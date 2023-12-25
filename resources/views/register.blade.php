<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Страница Регистрации</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f4f4f4;
        margin: 0;
        padding: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
    }

    .login-container {
        width: 230px;
        background-color: white;
        padding: 20px;
        border-radius: 5px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }

    .login-form {
        display: flex;
        flex-direction: column;
    }

    .login-form h2 {
        text-align: center;
        margin-bottom: 20px;
    }

    .form-control {
        margin-bottom: 10px;
    }

    .form-control label {
        display: block;
        margin-bottom: 5px;
    }

    .form-control input {
        width: 95%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .error-active {
        color: #e74c3c;
        margin-bottom: 15px;
        padding: 10px;
        background-color: #fce4e4;
        border: 1px solid #e74c3c;
        border-radius: 4px;
        text-align: center;
    }

    button {
        background-color: #0056b3;
        color: white;
        padding: 10px 15px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
        margin-top: 15px;
    }

    button:hover {
        background-color: #003d82;
    }
</style>
<div class="login-container">
    <form class="login-form">
        @csrf
        <h2>Регистрация</h2>

        <!-- Можно добавить сообщение об ошибке, если оно есть -->
        <!-- <div class="error-active">Сообщение об ошибке</div> -->

        <div class="form-control">
            <label for="name">Имя</label>
            <input type="text" id="name" name="name">
        </div>

        <div class="form-control">
            <label for="last_name">Фамилия</label>
            <input type="text" id="last_name" name="last_name">
        </div>

        <div class="form-control">
            <label for="email">Email</label>
            <input type="email" id="email" name="email">
        </div>

        <div class="form-control">
            <label for="password">Пароль</label>
            <input type="password" id="password" name="password">
        </div>

        <div>
            <label for="avatar">Аватар</label>
            <input type="file" id="avatar" name="avatar">
        </div>

        <button type="submit">Регистрация</button>
    </form>
</div>
<script>
    const form = document.querySelector('form')
    form.addEventListener('submit', function(event) {
        event.preventDefault();

        const formData = new FormData(form);

        axios.post('/register', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        })
            .then((response) => {
                localStorage.setItem('access_token', response.data.access_token)
                localStorage.setItem('centrifugo_token', response.data.centrifugo_token)
                localStorage.setItem('userId', response.data.user_id)
                window.location.href = '/';
            })
            .catch((error) => {
                const errorInput = form.querySelector('.error')
                errorInput.classList.add('error-active')
                errorInput.textContent = error.response.data.message
            });
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
</body>
</html>
