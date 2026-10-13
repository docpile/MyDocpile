PHP_ARG_ENABLE(mydocpile_runtime, whether to enable mydocpile-runtime support,
[  --enable-mydocpile-runtime           Enable mydocpile-runtime support])

if test "$PHP_MYDOCPILE_RUNTIME" != "no"; then
  PHP_NEW_EXTENSION(mydocpile_runtime, mydocpile_runtime.c, $ext_shared)
fi