from django.conf.urls import url

from blog import views


urlpatterns = [
    url(r'^$', views.index, name='blog'),
    url(r'^(?P<slug>[-\w]+)/$', views.BlogView.as_view(template_name='blog/post.html'), name='post'),
]