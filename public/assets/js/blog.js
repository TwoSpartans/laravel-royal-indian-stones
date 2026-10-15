
// Enter the number of blog to be shown 
number_of_blog_show = 4;


function set_id() {
    localStorage.setItem('blog_category_id', 'none');
    localStorage.setItem('blog_page_number', 'none');
    blog_show();
}

function set_blog_category_id(blog_category_id) {
    if (blog_category_id > 0) {
        localStorage.setItem('blog_category_id', blog_category_id);
        localStorage.setItem('blog_page_number', 'none');
        blog_show();
    }
}

function set_blog_page_number(blog_page_number) {
    localStorage.setItem('blog_page_number', blog_page_number);
    console.log(blog_page_number);
    blog_show();
}

function pagination_number_show() {
    blog_category_id = localStorage.getItem('blog_category_id');
    blog_page_number = localStorage.getItem('blog_page_number');
    jQuery.ajax({
        url: 'admin/pagination_number_show.php',
        type: 'POST',
        data: {
            blog_category_id: blog_category_id,
            blog_page_number: blog_page_number,
            number_of_blog_show: number_of_blog_show
        },
        success: function (data) {
            console.log(data)
            $('#blog_pagination').html(data);
            document.documentElement.scrollTop = 0;
        }
    });
}

function blog_show() {
    blog_category_id = localStorage.getItem('blog_category_id');
    blog_page_number = localStorage.getItem('blog_page_number');
    console.log(blog_category_id, blog_page_number);
    jQuery.ajax({
        url: 'admin/blog_show.php',
        type: 'POST',
        data: {
            blog_category_id: blog_category_id,
            blog_page_number: blog_page_number,
            number_of_blog_show: number_of_blog_show
        },
        success: function (data) {
            console.log(data)
            $('#blog_show').html(data);
            categories();
        }
    });
    pagination_number_show()
}

function categories() {
    jQuery.ajax({
        url: 'admin/categories.php',
        type: 'POST',
        success: function (data) {
            console.log(data);
            $('#categories').html(data);
            recent_post();
        }
    });
}

function recent_post() {
    jQuery.ajax({
        url: 'admin/recent_post.php',
        type: 'POST',
        success: function (data) {
            $('#recent_post').html(data);
        }
    });
}

function join_newsletter() {
    email_address = $('#email_address').val();
    jQuery.ajax({
        url: 'admin/join_newsletter.php',
        type: 'POST',
        data: {
            email_address: email_address
        },
        success: function (data) {
            $('#email_address_status').html('<b>' + data + '</b>');
            $('#email_address').val('');
        }
    });


}

//  Contact Form

function contact_form() {
    if ($('#name').val() != '' && $('#email').val() != '' && $('#subject').val() != '' && $('#message').val() != '') {
        document.getElementById("sent_message").style.display = "none";
        document.getElementById("error_message").style.display = "none";
        document.getElementById("loading").style.display = "block";
        name = $('#name').val();
        email = $('#email').val();
        subject = $('#subject').val();
        message = $('#message').val();

        jQuery.ajax({
            url: 'forms/contact.php',
            type: 'POST',
            data: {
                name: name,
                email: email,
                subject: subject,
                message: message
            },
            success: function (data) {

                if (data == true) {
                    document.getElementById("loading").style.display = "none";
                    $("#sent_message").fadeIn(2000);
                    console.log(data);
                    $('#name').val('');
                    $('#email').val('');
                    $('#subject').val('');
                    $('#message').val('');
                    fade_out();
                } else {
                    document.getElementById("loading").style.display = "none";
                    document.getElementById("error_message").innerText = data;
                    $("#error_message").fadeIn(2000);
                    console.log(data);
                    fade_out();
                }
            }
        });
    }
    else {
        document.getElementById("error_message").innerText = "Kindly fill the complete form";
        $("#error_message").fadeIn(2000);
        fade_out();
    }

}

//  Fade out div in 5 sec
function fade_out() {
    setTimeout(function () {
        $("#error_message").fadeOut(3000);
        $("#sent_message").fadeOut(3000);
    }, 5000);
};


// Product Bottom Data Show
function product_bottom() {
    console.log("Success");
    data = "Product Data"
    jQuery.ajax({
        url: 'admin/product_bottom.php',
        type: 'POST',
        data: {
            data : data
        },
        success: function (data) {
            console.log(data);
            $('#product_bottom').html(data);
        }
    });
}



