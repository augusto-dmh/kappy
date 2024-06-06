import { Router } from "express";

const router = new Router();

import user from "./user";
import token from "./token";

const routes = [user, token];

routes.forEach((route) => {
  router.use(route);
});

export default router;
