<!DOCTYPE html>
<html>
<head>
    <title>Test Login</title>
</head>
<body>
    <h2>Test Login Form</h2>
    
    <h3>Пользователи в базе данных:</h3>
    <ul>
        @foreach($users as $user)
            <li>ID: {{ $user->id }}, Email: {{ $user->email }}, Name: {{ $user->name }}</li>
        @endforeach
    </ul>
    
    <form method="POST" action="/test-login">
        @csrf
        <div>
            <label>Email:</label>
            <input type="email" name="email" value="roma.moscow.home@gmail.com" required>
        </div>
        <div>
            <label>Password:</label>
            <input type="password" name="password" value="любой" required>
            <small>(система автоматически попробует стандартные пароли)</small>
        </div>
        <button type="submit">Login</button>
    </form>
    
    @if(session('error'))
        <p style="color: red;">{{ session('error') }}</p>
    @endif
    
    @if(session('success'))
        <p style="color: green;">{{ session('success') }}</p>
    @endif
</body>
</html>