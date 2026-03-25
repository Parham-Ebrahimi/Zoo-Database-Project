const form = document.getElementById('form')
const firstname_input = document.getElementById('firstname-input')
const lastname_input = document.getElementById('lastname-input')
const email_input = document.getElementById('email-input')
const password_input = document.getElementById('password-input')
const repeat_password_input = document.getElementById('repeat-password-input')
const error_message = document.getElementById('error-message')
form.addEventListener('submit', (e)=> {
    
    let errors =  []

    if(firstname_input) {
        //if we have a firstname input then we are in the signup
        errors = getSignupFormErrors(firstname_input.value, lastname_input.value, email_input.value, password_input.value, repeat_password_input.value)
    }
    else {
        // if we dont have a firstname input then we are in the login
        errors = getLoginFormErros(email_input.value, password_input.value)
    }

    if(errors.length > 0) {
        e.preventDefault() //prevent submit
        error_message.innerText = errors.join(". ")
    }
})

function getSignupFormErrors(firstname, lastname, email, password, repeatPassword) {
    let errors = []

    if(firstname === '' || firstname == null) {
        errors.push("First name is required")
        firstname_input.parentElement.classList.add('incorrect')
    }

    if(lastname === '' || lastname == null) {
        errors.push("Last name is required")
        lastname_input.parentElement.classList.add('incorrect')
    }

    if(email === '' || email == null) {
        errors.push("Email is required")
        email_input.parentElement.classList.add('incorrect')
    }

    if(password === '' || password == null) {
        errors.push("Password is required")
        password_input.parentElement.classList.add('incorrect')
    }

    if(password.length < 9) {
        errors.push('Password must have at least 9 characters')
        password_input.parentElement.classList.add('incorrect')
    }

    if(password !== repeatPassword) {
        errors.push('Passwords do not match, Try Again')
        password_input.parentElement.classList.add('incorrect')
        repeat_password_input.parentElement.classList.add('incorrect')
    }

    return errors;
}

function getLoginFormErros(email, password) {
    let errors = []
    if(email === '' || email == null) {
        errors.push("Email is required")
        email_input.parentElement.classList.add('incorrect')
    }

    if(password === '' || password == null) {
        errors.push("Password is required")
        password_input.parentElement.classList.add('incorrect')
    }

    return errors;

}

const allInputs = [firstname_input, lastname_input, email_input, password_input, repeat_password_input]

allInputs.forEach(input =>  {
    input.addEventListener('input', () => {
        if(input.parentElement.classList.contains('incorrect')) {
            input.parentElement.classList.remove('incorrect')
            error_message.innerText = ''
        }
    })
})