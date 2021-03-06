<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Foto;
use App\Form\RegistrationFormType;
use App\Repository\MensajesRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
/**
 * @Route("/user")
 */
class UserController extends AbstractController
{

    /**
     * @Route("/", name="user_index", methods={"GET"})
     */
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

  /**
     * @Route("/panel", name="admin_panel", methods={"GET"})
     */
    public function panelAdmin()
    {
        $user =$this->getUser();
        return $this->render('admin/panelAdmin.html.twig', [
            'userActive' => $user,
        ]);
    }
    /**
     * @Route("/{id}", name="user_show", methods={"GET"})
     */
    public function show(User $user): Response
    {
        $preferencias = $user->getPreferencias();
        $fotos = $user->getFoto();
        return $this->render('admin/user/show.html.twig', [
            'user' => $user,
            'preferencias' => $preferencias,
            'fotos' => $fotos

        ]);
    }

    /**
     * @Route("/{id}/edit", name="user_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, User $user): Response
    {
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $fotoFile =  $form->get('foto')->getData();
            if ($fotoFile != null) {
                self::renamePic($user, $fotoFile);
            } else {
                $this->getDoctrine()->getManager()->flush();
            }

            return $this->redirectToRoute('user_index');
        }

        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'registrationForm' => $form->createView(),
        ]);
    }
    

    /**
     * @Route("/{id}", name="user_delete", methods={"DELETE"})
     */
    public function delete(Request $request, User $user, MensajesRepository $mensajeRepository): Response
    {
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            try{
                self::rrmdir('users/user'.$user->getId());
            }catch(IOExceptionInterface $exception){
                echo "An error occurred while creating your directory at " . $exception->getPath();
            }

            $entityManager = $this->getDoctrine()->getManager();
            $fotos = $user->getFoto();
            if ($fotos){
                foreach ($fotos as $foto) {
                $entityManager->remove($foto);
                }
            }
            
            $idUserActivo = $user->getId();
            $enviados = $mensajeRepository->chatConversation($idUserActivo);
            if ($enviados){
                foreach ($enviados as $clave => $objMensaje) {
                $entityManager->remove($objMensaje);
                }
            }
            $entityManager->remove($user);
            $entityManager->flush();
        }

        return $this->redirectToRoute('user_index');
    }
    
    /**
     * @Route("/foto/{id}", name="pic_delete", methods={"DELETE"})
     */
    public function deletePicture(Request $request, Foto $foto): Response
    {
        $userId = $_POST['idUsuario'];

        $directorio = 'users/user' . $userId;
        $nombreFoto = $directorio.'/'.$foto->getNombre();
        $filesystem = new Filesystem();

        if ($this->isCsrfTokenValid('delete' . $foto->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($foto);
            $entityManager->flush();
            try {
                $filesystem->remove($nombreFoto);
                if (self::is_dir_empty($directorio)) {
                    rmdir($directorio);
                }
            } catch (IOExceptionInterface $exception) {
                echo "An error occurred while creating your directory at " . $exception->getPath();
            }
        }
        $redireccion = new RedirectResponse('/user/'.$userId.'/edit');
        return $redireccion;
    }

    private function renamePic(User $user, $fotoFile)
    {
        $entityManager = $this->getDoctrine()->getManager();

        $foto = new Foto();
        $foto->setNombre($fotoFile->getClientOriginalName());
        $entityManager->persist($foto);
        $entityManager->flush();

        $idFoto = $foto->getId();
        $fileName = 'img' . $user->getId() . '-' . $idFoto . '.' . $fotoFile->guessExtension();

        $fotoFile->move('users/user' . $user->getId(), $fileName);
        $foto->setNombre($fileName);
        $entityManager->flush();

        $user->addFoto($foto);
        $entityManager->flush();
    }

    function is_dir_empty($dir)
    {
        if (!is_readable($dir)) return NULL;
        $handle = opendir($dir);
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != "..") {
                return FALSE;
            }
        }
        return TRUE;
    }

    function rrmdir($src) {
        if (file_exists ( $src ) ){
            $dir = opendir($src);
                    while (false !== ($file = readdir($dir))) {
                        if (($file != '.') && ($file != '..')) {
                            $full = $src . '/' . $file;
                            if (is_dir($full)) {
                                rrmdir($full);
                            } else {
                                unlink($full);
                            }
                        }
                    }
                    closedir($dir);
                    rmdir($src);
        }
    }
}
