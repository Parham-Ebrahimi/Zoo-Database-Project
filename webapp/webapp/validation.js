const form = document.getElementById('form')
const username_input = document.getElementById('username-input')
const password_input = document.getElementById('password-input')
const error_message = document.getElementById('error-message')

form.addEventListener('submit', (e) => {
    let errors = []
    errors = getLoginFormErrors(username_input.value, password_input.value)

    if (errors.length > 0) {
        e.preventDefault()
        error_message.innerText = errors.join(". ")
    }
})

function getLoginFormErrors(username, password) {
    let errors = []

    if (username === '' || username == null) {
        errors.push("Username is required")
        username_input.parentElement.classList.add('incorrect')
    } else {
        username_input.parentElement.classList.remove('incorrect')
    }

    if (password === '' || password == null) {
        errors.push("Password is required")
        password_input.parentElement.classList.add('incorrect')
    } else {
        password_input.parentElement.classList.remove('incorrect')
    }

    return errors
}

const allInputs = [username_input, password_input]

allInputs.forEach(input => {
    input.addEventListener('input', () => {
        if (input.parentElement.classList.contains('incorrect')) {
            input.parentElement.classList.remove('incorrect')
            error_message.innerText = ''
        }
    })
})