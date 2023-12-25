<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в систему</title>
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
        width: 100%;
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

    .btn {
        background-color: #0056b3;
        margin-top: 10px;
        color: white;
        padding: 10px 15px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
        text-decoration: none;
    }

    .btn:hover {
        background-color: #003d82;
    }
</style>
<div class="login-container">
    <form class="login-form" action="{{route('auth')}}">
        @csrf
        <h2>Вход в систему</h2>
        <div class="form-control">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-control">
            <label for="password">Пароль</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="error"></div>
        <button class="btn login-btn" type="button">Войти</button>
        <a href="{{route('register-page')}}" class="btn register-btn" type="button">Зарегистрироваться</a>
    </form>
</div>
<script>
    const form = document.querySelector('.login-form')
    const btn = form.querySelector('.login-btn')
    btn.addEventListener('click', () => {
        const emailInput = form.querySelector('#email');
        const passwordInput = form.querySelector('#password');
        axios.post('/login', {
            email: emailInput.value,
            password: passwordInput.value
        }).then((response) => {
            localStorage.setItem('access_token', response.data.access_token)
            localStorage.setItem('centrifugo_token', response.data.centrifugo_token)
            localStorage.setItem('userId', response.data.user_id)
            window.location.href = '/';
        }).catch((error) => {
            const errorInput = form.querySelector('.error')
            errorInput.classList.add('error-active')
            errorInput.textContent = error.response.data.message
        });
    })
</script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
</body>
</html>

